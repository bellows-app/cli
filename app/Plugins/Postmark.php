<?php

namespace App\Plugins;

use App\Bellows\Data\AddApiCredentialsPrompt;
use App\Bellows\Plugin;
use App\Bellows\Util\Domain;
use Illuminate\Http\Client\PendingRequest;
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

    protected $verifyReturnPath = false;

    protected $verifyDKIM = false;

    public function setup($server): void
    {
        $this->http->createJsonClient(
            'https://api.postmarkapp.com/',
            fn (PendingRequest $request, array $credentials) => $request->withHeaders([
                'X-Postmark-Account-Token' => $credentials['token'],
            ]),
            new AddApiCredentialsPrompt(
                url: 'https://account.postmarkapp.com/api_tokens',
                helpText: 'Retrieve your *Account* token here.',
                credentials: ['token'],
            ),
        );

        $this->server        = $this->getServer();
        $this->sendingDomain = $this->getDomain();

        $this->updateDomainRecordsWithProvider();

        $this->messageStreamId = $this->getMessageStreamId();

        $this->fromEmail = $this->console->ask(
            'From email',
            "hello@{$this->sendingDomain['Name']}"
        );
    }

    public function getMessageStreamId()
    {
        $token = $this->server['ApiTokens'][0];

        $streams = collect(
            Http::withHeaders([
                'X-Postmark-Server-Token' => $token,
            ])
                ->acceptJson()
                ->asJson()
                ->get('https://api.postmarkapp.com/message-streams')
                ->json()['MessageStreams']
        );

        $choices = $streams->mapWithKeys(fn ($s) => [$s['ID'] => "{$s['Name']} ({$s['Description']})"])->toArray();

        if (count($choices) === 1) {
            return array_key_first($choices);
        }

        return $this->console->choice(
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

        if (!$this->dnsProvider) {
            $this->console->info('Skipping DNS verification as no DNS provider is configured.');
            return;
        }

        if (!$this->sendingDomain['ReturnPathDomainVerified']) {
            $this->console->info('Adding ReturnPath record to ' . $this->dnsProvider->getName());

            $this->dnsProvider->addCNAMERecord(
                name: 'pm-bounces.' . Domain::getSubdomain($this->sendingDomain['Name']),
                value: $this->sendingDomain['ReturnPathDomainCNAMEValue'],
                ttl: 1800,
            );

            $this->verifyReturnPath = true;
        }

        if (!$this->sendingDomain['DKIMVerified']) {
            $this->console->info('Adding DKIM record to ' . $this->dnsProvider->getName());

            $this->dnsProvider->addTXTRecord(
                name: Domain::getSubdomain($this->sendingDomain['DKIMPendingHost']),
                value: $this->sendingDomain['DKIMPendingTextValue'],
                ttl: 1800,
            );

            $this->verifyDKIM = true;
        }
    }

    public function getServer()
    {
        if ($this->console->confirm('Create new Postmark server?', true)) {
            $name = $this->console->ask('Server name', $this->projectConfig->appName);
            $color = $this->console->choice(
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

            return $this->http->client()->post('servers', [
                'Name'  => $name,
                'Color' => $color,
            ])->json();
        }

        $servers = collect(
            $this->http->client()->get('servers', [
                'count'  => 200,
                'offset' => 0,
            ])->json()['Servers']
        );

        $serverChoices = $servers->sortBy('Name')->pluck('Name');

        $default = $servers->first(
            fn ($server) => $server['Name'] === $this->projectConfig->appName
        );

        $serverChoice = $this->console->choice(
            'Choose a Postmark server',
            $serverChoices->toArray(),
            $default['Name'] ?? null,
        );

        return $servers->first(fn ($server) => $server['Name'] === $serverChoice);
    }

    public function getDomain()
    {
        if ($this->console->confirm('Create new Postmark domain?', true)) {
            $name = $this->console->ask('Domain name', "mail.{$this->projectConfig->domain}");

            return $this->http->client()->post('domains', [
                'Name'         => $name,
            ])->json();
        }

        $domains = collect(
            $this->http->client()->get('domains', [
                'count'  => 200,
                'offset' => 0,
            ])->json()['Domains']
        );

        $domainChoices = $domains->sortBy('Name')->pluck('Name');

        $default = $domains->first(
            fn ($domain) => Str::contains($domain['Name'], $this->projectConfig->domain),
        );

        $domainChoice = $this->console->choice(
            'Choose a Postmark sender domain',
            $domainChoices->toArray(),
            $default['Name'] ?? null,
        );

        $domainId = $domains->first(fn ($domain) => $domain['Name'] === $domainChoice)['ID'];

        return $this->http->client()->get("domains/{$domainId}")->json();
    }

    public function wrapUp($server, $site): void
    {
        if ($this->verifyReturnPath) {
            $this->console->info('Verifying Postmark ReturnPath record...');
            $this->http->client()->put("domains/{$this->sendingDomain['ID']}/verifyReturnPath");
        }

        if ($this->verifyDKIM) {
            $this->console->info('Verifying Postmark DKIM record...');
            $this->http->client()->put("domains/{$this->sendingDomain['ID']}/verifyDkim ");
        }
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
