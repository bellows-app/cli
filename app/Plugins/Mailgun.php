<?php

namespace App\Plugins;

use App\DeployMate\Data\AddApiCredentialsPrompt;
use App\DeployMate\Plugin;
use App\DeployMate\Util\Domain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Mailgun extends Plugin
{
    public $priority = 100;

    protected string $domain;

    protected string $endpoint;

    protected array $requiredComposerPackages = [
        'symfony/mailgun-mailer',
    ];

    public function setup($server): void
    {
        $region = $this->console->choice('Which region is your Mailgun account in?', [
            'US',
            'EU',
        ]);

        $this->endpoint = $region === 'US' ? 'api.mailgun.net' : 'api.eu.mailgun.net';

        $this->http->createClient(
            "https://{$this->endpoint}/v4",
            fn (PendingRequest $request, array $credentials) => $request->asForm()->withBasicAuth('api', $credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://app.mailgun.com/app/account/security/api_keys',
                helpText: 'Make sure you select your *Private API key*',
                credentials: ['token'],
            )
        );

        if ($this->console->confirm('Create a new domain?', true)) {
            $this->createDomain();
        } else {
            $this->selectDomain();
        }
    }

    protected function createDomain()
    {
        $domain = $this->console->ask('What is the domain name?', 'mail.' . $this->projectConfig->domain);

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
        $this->console->info('Updating DNS records...');

        collect($result['sending_dns_records'])->each(function ($record) {
            $args = [
                Domain::getSUbdomain($record['name']),
                $record['value'],
                1800,
            ];

            ray($args);

            match ($record['record_type']) {
                'TXT'   => $this->dnsProvider->addTXTRecord(...$args),
                'CNAME' => $this->dnsProvider->addCNAMERecord(...$args),
            };
        });

        $this->console->info('Verifying domain with Mailgun...');

        $this->http->client()->put("domains/{$this->domain}/verify");
    }

    protected function selectDomain()
    {
        $response = $this->http->client()->get('domains')->json();

        $domainChoices = collect($response['items'])->map(fn ($domain) => $this->domainChoice($domain))->toArray();

        $domainChoice = $this->console->choice('Which domain do you want to use?', $domainChoices);

        $domain = collect($response['items'])->first(fn ($domain) => $this->domainChoice($domain) === $domainChoice);

        $this->domain = $domain['name'];
    }

    protected function domainChoice($domain)
    {
        return "{$domain['name']} ({$domain['type']})";
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'MAILGUN_DOMAIN'   => $this->domain,
            'MAILGUN_SECRET'   => $this->getApiConfig($this->domain)['token'],
            'MAILGUN_ENDPOINT' => $this->endpoint,
        ];
    }
}
