<?php

namespace Bellows\Dns;

use Bellows\Config;
use Bellows\Console;
use Bellows\Enums\DnsRecordType;
use Bellows\InteractsWithConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class DnsProvider
{
    use InteractsWithConfig;

    protected string $baseDomain;

    protected string $apiBaseUrl;

    protected string $apiHost;

    public function __construct(
        protected Config $config,
        protected string $domain,
        protected Console $console,
    ) {
        $this->baseDomain = Str::of($domain)->explode('.')->slice(-2)->implode('.');
        $this->apiHost = parse_url($this->apiBaseUrl, PHP_URL_HOST);
    }

    abstract public static function getNameServerDomain(): string;

    abstract protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool;

    abstract protected function addNewCredentials(): array;

    abstract protected function accountHasDomain(array $credentials): bool;

    abstract protected function getClient(array $credentials): PendingRequest;

    abstract protected function testApiCall(array $credentials): bool;

    public function getName()
    {
        return class_basename($this);
    }

    public function setCredentials(): void
    {
        $config = $this->getApiConfig($this->apiHost);

        if (!$config) {
            $this->setUpNewCredentials();
            return;
        }

        $credentials = collect($config)->first(fn ($creds) => $this->accountHasDomain($creds));

        if (!$credentials) {
            $this->console->line('No account found for this domain.');

            if (!$this->console->confirm('Do you want to add a new account?', true)) {
                return;
            }

            $this->setUpNewCredentials();
            return;
        }

        $this->console->line('Found account for this domain: ' . collect($config)->search($credentials));

        $this->setClient($this->getClient($credentials));
    }

    public static function matchByNameserver(string $nameserver): bool
    {
        return Str::contains($nameserver, static::getNameServerDomain());
    }

    protected function setUpNewCredentials()
    {
        $credentials = $this->addNewCredentials();

        if ($this->testApiCall($credentials)) {
            $name = $this->console->ask(
                'Account Name',
                $this->getDefaultNewAccountName($credentials)
            );

            $this->setConfig($name, $credentials);
        } else {
            $this->console->warn('It seems that your credentials are invalid, try again.');
        }

        $this->setCredentials();
    }

    protected function getDefaultNewAccountName(array $credentials): ?string
    {
        return null;
    }

    protected function setConfig(string $name, array $payload)
    {
        $this->setApiConfig($this->apiHost, $name, $payload);
    }

    protected function setClient(PendingRequest $base)
    {
        Http::macro('dnsProvider', fn () => $base);
    }

    public function addARecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(DnsRecordType::A, $name ? $name : '@', $value, $ttl);
    }

    public function addTXTRecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(DnsRecordType::TXT, $name, $value, $ttl);
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(DnsRecordType::CNAME, $name, $value, $ttl);
    }

    public function getARecord(string $name): ?string
    {
        return $this->getRecord(DnsRecordType::A, $name);
    }

    public function getTXTRecord(string $name): ?string
    {
        return $this->getRecord(DnsRecordType::TXT, $name);
    }

    public function getCNAMERecord(string $name): ?string
    {
        return $this->getRecord(DnsRecordType::CNAME, $name);
    }

    abstract protected function getRecord(DnsRecordType $type, string $name): ?string;
}