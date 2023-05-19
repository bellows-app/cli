<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Dns\DnsProvider;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Http as BellowsHttp;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Util\Domain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Postmark extends Plugin implements Launchable, Deployable
{
    protected array $postmarkServer;

    protected array $sendingDomain;

    protected string $messageStreamId;

    protected string $fromEmail;

    protected array $requiredComposerPackages = [
        'symfony/postmark-mailer',
    ];

    protected $verifyReturnPath = false;

    protected $verifyDKIM = false;

    public function __construct(
        protected BellowsHttp $http,
        protected ?DnsProvider $dnsProvider = null,
    ) {
    }

    public function launch(): void
    {
        $this->http->createJsonClient(
            'https://api.postmarkapp.com/',
            fn (PendingRequest $request, array $credentials) => $request->withHeaders([
                'X-Postmark-Account-Token' => $credentials['token'],
            ]),
            new AddApiCredentialsPrompt(
                url: 'https://account.postmarkapp.com/api_tokens',
                helpText: 'Retrieve your <comment>Account</comment> token here.',
                credentials: ['token'],
                displayName: 'Postmark',
            ),
            fn (PendingRequest $request) => $request->get('servers', ['count' => 1, 'offset' => 0]),
        );

        $this->postmarkServer = $this->getServer();
        $this->sendingDomain = $this->getDomain();

        $this->updateDomainRecordsWithProvider();

        $this->messageStreamId = $this->getMessageStreamId();

        $this->fromEmail = Console::ask(
            'From email',
            "hello@{$this->sendingDomain['Name']}"
        );
    }

    public function deploy(): void
    {
    }

    public function getMessageStreamId()
    {
        $token = $this->postmarkServer['ApiTokens'][0];

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

        return Console::choice(
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
            Console::warn('Skipping DNS verification as no DNS provider is configured.');

            return;
        }

        if (!$this->sendingDomain['ReturnPathDomainVerified']) {
            Console::miniTask('Adding ReturnPath record to', $this->dnsProvider->getName());

            $this->dnsProvider->addCNAMERecord(
                name: 'pm-bounces.' . Domain::getSubdomain($this->sendingDomain['Name']),
                value: $this->sendingDomain['ReturnPathDomainCNAMEValue'],
                ttl: 1800,
            );

            $this->verifyReturnPath = true;
        }

        if (!$this->sendingDomain['DKIMVerified']) {
            Console::miniTask('Adding DKIM record to ', $this->dnsProvider->getName());

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
        if (Console::confirm('Create new Postmark server?', true)) {
            $name = Console::ask('Server name', Project::config()->appName);

            $color = Console::choice(
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

        return Console::choiceFromCollection(
            'Choose a Postmark server',
            $servers->sortBy('Name'),
            'Name',
            Project::config()->appName,
        );
    }

    public function getDomain()
    {
        if (Console::confirm('Create new Postmark domain?', true)) {
            $name = Console::ask('Domain name', 'mail.' . Project::config()->domain);

            return $this->http->client()->post('domains', [
                'Name' => $name,
            ])->json();
        }

        $domains = collect(
            $this->http->client()->get('domains', [
                'count'  => 200,
                'offset' => 0,
            ])->json()['Domains']
        );

        $domainId = Console::choiceFromCollection(
            'Choose a Postmark sender domain',
            $domains->sortBy('Name'),
            'Name',
            fn ($domain) => Str::contains($domain['Name'], Project::config()->domain),
        )['ID'];

        return $this->http->client()->get("domains/{$domainId}")->json();
    }

    public function canDeploy(): bool
    {
        return $this->site->getEnv()->get('MAIL_MAILER') !== 'postmark'
            || !$this->site->getEnv()->hasAll('POSTMARK_MESSAGE_SREAM_ID', 'POSTMARK_TOKEN');
    }

    public function wrapUp(): void
    {
        if ($this->verifyReturnPath) {
            $this->http->client()->put("domains/{$this->sendingDomain['ID']}/verifyReturnPath");
        }

        if ($this->verifyDKIM) {
            $this->http->client()->put("domains/{$this->sendingDomain['ID']}/verifyDkim ");
        }
    }

    public function environmentVariables(): array
    {
        return [
            'MAIL_MAILER'                => 'postmark',
            'MAIL_FROM_ADDRESS'          => $this->fromEmail,
            'POSTMARK_MESSAGE_STREAM_ID' => $this->messageStreamId,
            'POSTMARK_TOKEN'             => $this->postmarkServer['ApiTokens'][0],
        ];
    }
}
