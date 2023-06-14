<?php

namespace Bellows\Dns;

use Bellows\Config;
use Bellows\Config\InteractsWithConfig;
use Bellows\Enums\DnsRecordType;
use Bellows\PluginSdk\Facades\Console;
use Bellows\Safety\PreventsCallingFromPlugin;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class DnsProvider
{
    use InteractsWithConfig, PreventsCallingFromPlugin;

    protected string $baseDomain;

    protected string $apiBaseUrl;

    protected string $apiHost;

    public function __construct(
        protected Config $config,
        protected string $domain,
    ) {
        $this->baseDomain = Str::of($domain)->explode('.')->slice(-2)->implode('.');
        $this->apiHost = parse_url($this->apiBaseUrl, PHP_URL_HOST);
    }

    public static function matchByNameserver(string $nameserver): bool
    {
        return Str::contains($nameserver, static::getNameServerDomain());
    }

    abstract public static function getNameServerDomain(): string;

    public function getName(): string
    {
        return class_basename($this);
    }

    public function setCredentials(): bool
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $config = $this->getAllConfigsForApi($this->apiHost);

        if (!$config) {
            if (Console::confirm('Do you want to allow Bellows to manage your DNS records?', true)) {
                return $this->setUpNewCredentials();
            }

            return false;
        }

        $credentials = collect($config)->first(fn ($creds) => $this->accountHasDomain($creds));

        if (!$credentials) {
            Console::warn('No account found for this domain.');

            if (Console::confirm('Do you want to add a new account?', true)) {
                return $this->setUpNewCredentials();
            }

            return false;
        }

        Console::miniTask('Found account for this domain', collect($config)->search($credentials));

        $this->setClient($this->getClient($credentials));

        return true;
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

    abstract protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool;

    abstract protected function addNewCredentials(): array;

    abstract protected function accountHasDomain(array $credentials): bool;

    abstract protected function getClient(array $credentials): PendingRequest;

    abstract protected function testApiCall(array $credentials): bool;

    protected function setUpNewCredentials(): bool
    {
        $credentials = $this->addNewCredentials();

        if ($this->testApiCall($credentials)) {
            $name = Console::ask(
                'Account Name',
                $this->getDefaultNewAccountName($credentials)
            );

            $this->setConfig($name, $credentials);
        } else {
            Console::warn('It seems that your credentials are invalid, try again.');
        }

        return $this->setCredentials();
    }

    protected function withTrailingDot(string $value): string
    {
        if ($value === '@') {
            return $value;
        }

        return Str::of($value)->trim('.')->wrap('', '.')->toString();
    }

    protected function getDefaultNewAccountName(array $credentials): ?string
    {
        return null;
    }

    protected function setConfig(string $name, array $payload): void
    {
        $this->setApiConfigValue($this->apiHost, $name, $payload);
    }

    protected function setClient(PendingRequest $base): void
    {
        Http::macro('dnsProvider', fn () => $base);
    }

    abstract protected function getRecord(DnsRecordType $type, string $name): ?string;
}
