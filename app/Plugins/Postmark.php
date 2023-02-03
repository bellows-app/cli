<?php

namespace App\Plugins;

use App\DeployMate\NewTokenPrompt;
use App\DeployMate\Plugin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Postmark extends Plugin
{
    protected $server;

    protected $sendingDomain;

    protected string $messageStreamId;

    protected string $fromEmail;

    protected array $requiredComposerPackages = [
        'symfony/postmark-mailer',
    ];

    public function setup($server): void
    {
        $token = $this->askForToken(
            newTokenPrompt: new NewTokenPrompt(
                url: 'https://account.postmarkapp.com/api_tokens',
                helpText: 'Retrieve your *Account* token here.',
            ),
        );

        Http::macro(
            'postmark',
            fn () => Http::baseUrl('https://api.postmarkapp.com/')
                ->withHeaders([
                    'X-Postmark-Account-Token' => $token,
                ])
                ->acceptJson()
                ->asJson()
        );

        $this->server        = $this->getServer();
        $this->sendingDomain = $this->getDomain();

        $this->updateDomainRecordsWithProvider();

        $this->messageStreamId = $this->getMessageStreamId();

        $this->fromEmail = $this->ask(
            'From email',
            "hello@{$this->sendingDomain['Name']}"
        );
    }

    public function getMessageStreamId()
    {
        $token = $this->server['ApiTokens'][0];

        Http::macro(
            'postmarkServer',
            fn () => Http::baseUrl('https://api.postmarkapp.com/')
                ->withHeaders([
                    'X-Postmark-Server-Token' => $token,
                ])
                ->acceptJson()
                ->asJson()
        );

        $streams = collect(
            Http::postmarkServer()->get('message-streams')->json()['MessageStreams']
        );

        $choices = $streams->mapWithKeys(fn ($s) => [$s['ID'] => "{$s['Name']} ({$s['Description']})"])->toArray();

        if (count($choices) === 1) {
            return array_key_first($choices);
        }

        return $this->choice(
            'Which Postmark message stream',
            $choices,
            'outbound',
        );
    }

    public function updateDomainRecordsWithProvider()
    {
        if (
            $this->sendingDomain['ReturnPathDomainVerified']
            && $this->sendingDomain['DKIMVerified']
        ) {
            // Nothing to do here, we good.
            return;
        }

        $configKey = $this->askForToken(
            'digitalocean',
            'Which DigitalOcean configuration'
        );

        $token = config("forge.digitalocean.{$configKey}");

        Http::macro(
            'pmDigitalOcean',
            fn () => Http::baseUrl('https://api.digitalocean.com/v2/')
                ->withToken($token)
                ->acceptJson()
                ->asJson()
        );

        $mainDomain = collect(
            explode('.', $this->sendingDomain['Name'])
        )->slice(-2)->implode('.');

        try {
            Http::pmDigitalOcean()->get("domains/{$mainDomain}")->json()['domain'];
        } catch (\Exception $e) {
            $this->info('Error checking for domain in DigitalOcean: ' . $e->getMessage());
            return;
        }

        if (!$this->sendingDomain['ReturnPathDomainVerified']) {
            $this->info('Adding ReturnPath record to DigitalOcean');

            $response = Http::pmDigitalOcean()->post("domains/{$mainDomain}/records", [
                'type' => 'CNAME',
                'name' => 'pm-bounces.' . $this->getSubdomain($this->sendingDomain['Name']),
                'data' => $this->sendingDomain['ReturnPathDomainCNAMEValue'] . (Str::endsWith($this->sendingDomain['ReturnPathDomainCNAMEValue'], '.') ? '' : '.'),   // DigitalOcean requires a trailing dot
                'ttl'  => 1800,
            ]);
        }

        if (!$this->sendingDomain['DKIMVerified']) {
            $this->info('Adding DKIM record to DigitalOcean');

            Http::pmDigitalOcean()->post("domains/{$mainDomain}/records", [
                'type'     => 'TXT',
                'name'     => $this->getSubDomain($this->sendingDomain['DKIMPendingHost']),
                'data'     => $this->sendingDomain['DKIMPendingTextValue'],
                'ttl'      => 1800,
            ]);
        }
    }

    protected function getSubDomain(string $domain)
    {
        return collect(explode('.', $domain))->slice(0, -2)->implode('.');
    }

    public function getServer()
    {
        if ($this->confirm('Create new Postmark server?', true)) {
            $name = $this->ask('Server name', $this->projectConfig->appName);
            $color = $this->choice(
                'Server color',
                [
                    'Blue',
                    'Green',
                    'Grey',
                    'Orange',
                    'Purple',
                    'Red',
                    'Turquoise',
                    'Yellow',
                ],
                'Blue'
            );

            return Http::postmark()->post('servers', [
                'Name'  => $name,
                'Color' => $color,
            ])->json();
        }

        $servers = collect(
            Http::postmark()->get('servers', [
                'count'  => 200,
                'offset' => 0,
            ])->json()['Servers']
        );

        // This is dumb, why are we not just returning the ID as a number?
        // Because this function won't return the ID as the choice answer if it's numeric.
        // And no, casting it to a string doesn't work either.
        $serverChoices = $servers->sortBy('Name')->mapWithKeys(
            fn ($server) => ["ID-{$server['ID']}" => $server['Name']]
        );

        $default = $servers->first(
            fn ($server) => $server['Name'] === $this->projectConfig->appName
        );

        $serverId = $this->choice(
            'Choose a Postmark server',
            $serverChoices->toArray(),
            $default ? "ID-{$default['ID']}" : null,
        );

        return $servers->first(
            fn ($server) => (string) $server['ID'] === Str::replace('ID-', '', $serverId),
        );
    }

    public function getDomain()
    {
        if ($this->confirm('Create new Postmark domain?', true)) {
            $name = $this->ask('Domain name', "mail.{$this->projectConfig->domain}");

            return Http::postmark()->post('domains', [
                'Name'         => $name,
            ])->json();
        }

        $domains = collect(
            Http::postmark()->get('domains', [
                'count'  => 200,
                'offset' => 0,
            ])->json()['Domains']
        );

        // See comment above for the reasoning here
        $domainChoices = $domains->sortBy('Name')->mapWithKeys(
            fn ($domain) => ["ID-{$domain['ID']}" => $domain['Name']]
        );

        $default = $domains->first(
            fn ($domain) => Str::contains($domain['Name'], $this->projectConfig->domain),
        );

        $domainId = $this->choice(
            'Choose a Postmark sender domain',
            $domainChoices->toArray(),
            $default ? "ID-{$default['ID']}" : null,
        );

        $domainId = Str::replace('ID-', '', $domainId);

        return Http::postmark()->get("domains/{$domainId}")->json();
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'MAIL_MAILER'                => 'postmark',
            'MAIL_FROM_ADDRESS'          => $this->fromEmail,
            'POSTMARK_MESSAGE_STREAM_ID' => $this->messageStreamId,
            'POSTMARK_TOKEN'             => $this->server['ApiTokens'][0],
        ];
    }
}
