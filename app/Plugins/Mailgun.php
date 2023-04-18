<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Dns\DnsProvider;
use Bellows\Facades\Console;
use Bellows\Http;
use Bellows\Plugin;
use Bellows\Project;
use Bellows\Util\Domain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Mailgun extends Plugin
{
    protected string $domain;

    protected string $endpoint;

    protected bool $verifyNewDomain = false;

    protected array $requiredComposerPackages = [
        'symfony/mailgun-mailer',
    ];

    public function __construct(
        protected Http $http,
        protected Project $project,
        protected ?DnsProvider $dnsProvider,
    ) {
    }

    public function setup(): void
    {
        $region = Console::choice('Which region is your Mailgun account in?', [
            'US',
            'EU',
        ]);

        $this->endpoint = $region === 'US' ? 'api.mailgun.net' : 'api.eu.mailgun.net';

        $this->http->createClient(
            "https://{$this->endpoint}/v4",
            fn (PendingRequest $request, array $credentials) => $request->asForm()->withBasicAuth('api', $credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://app.mailgun.com/app/account/security/api_keys',
                helpText: 'Make sure you select your <comment>Private API key</comment>',
                credentials: ['token'],
                displayName: 'Mailgun',
            ),
            fn (PendingRequest $request) => $request->get('domains', ['limit' => 1]),
        );

        if (Console::confirm('Create a new domain?', true)) {
            $this->createDomain();
        } else {
            $this->selectDomain();
        }
    }

    public function wrapUp(): void
    {
        if ($this->verifyNewDomain) {
            $this->http->client()->put("domains/{$this->domain}/verify");
        }
    }

    public function environmentVariables(): array
    {
        return [
            'MAILGUN_DOMAIN'   => $this->domain,
            'MAILGUN_SECRET'   => $this->http->client()->getOptions()['auth'][1],
            'MAILGUN_ENDPOINT' => $this->endpoint,
        ];
    }

    protected function createDomain()
    {
        $domain = Console::ask('What is the domain name?', 'mail.' . $this->project->config->domain);

        $result = $this->http->client()->post('domains', [
            'name' => $domain,
        ]);

        $this->domain = $domain;

        if ($this->dnsProvider && Arr::get($result, 'sending_dns_records')) {
            $this->updateDnsRecords($result);
        }
    }

    protected function updateDnsRecords($result)
    {
        Console::info('Updating DNS records...');

        collect($result['sending_dns_records'])->each(function ($record) {
            $args = [
                Domain::getSubdomain($record['name']),
                $record['value'],
                1800,
            ];

            match ($record['record_type']) {
                'TXT'   => $this->dnsProvider->addTXTRecord(...$args),
                'CNAME' => $this->dnsProvider->addCNAMERecord(...$args),
            };
        });

        $this->verifyNewDomain = true;
    }

    protected function selectDomain()
    {
        $response = $this->http->client()->get('domains')->json();

        $domainChoices = collect($response['items'])->map(fn ($domain) => array_merge(
            $domain,
            ['custom_key' => "{$domain['name']} ({$domain['type']})"]
        ));

        $domain = Console::choiceFromCollection(
            'Which domain do you want to use?',
            $domainChoices,
            'custom_key',
            fn ($domain) => Str::contains($domain['name'], $this->project->config->domain),
        );

        $this->domain = $domain['name'];
    }
}
