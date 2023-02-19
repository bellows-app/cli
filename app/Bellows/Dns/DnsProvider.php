<?php

namespace App\Bellows\Dns;

use App\Bellows\Config;
use App\Bellows\Console;
use App\Bellows\Enums\DnsRecordType;
use App\Bellows\InteractsWithConfig;
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

    abstract protected function addNewCredentials(): void;

    abstract protected function accountHasDomain(array $credentials): bool;

    abstract protected function getClient(array $credentials): PendingRequest;

    abstract protected function testApiCall(): bool;

    public function getName()
    {
        return class_basename($this);
    }

    public function setCredentials(): void
    {
        $config = $this->getApiConfig($this->apiHost);

        if (!$config) {
            $this->addNewCredentials();
            $this->setCredentials();

            if (!$this->testApiCall()) {
                $this->console->line('Something went wrong, please try again.');
                $this->setCredentials();
            }

            return;
        }

        $credentials = collect($config)->first(fn ($creds) => $this->accountHasDomain($creds));

        if (!$credentials) {
            $this->console->line('No account found for this domain.');

            if (!$this->console->confirm('Do you want to add a new account?', true)) {
                return;
            }

            $this->addNewCredentials();
            $this->setCredentials();

            if (!$this->testApiCall()) {
                $this->console->line('Something went wrong, please try again.');
                $this->setCredentials();
            }

            return;
        }

        $this->console->line('Found account for this domain: ' . collect($config)->search($credentials));

        $this->setClient($this->getClient($credentials));
    }

    public static function matchByNameserver(string $nameserver): bool
    {
        return Str::contains($nameserver, static::getNameServerDomain());
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
