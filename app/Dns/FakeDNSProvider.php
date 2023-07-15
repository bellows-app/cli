<?php

namespace Bellows\Dns;

use Bellows\Enums\DnsRecordType;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FakeDNSProvider extends AbstractDnsProvider
{
    protected string $apiBaseUrl = '';

    protected string $zoneId;

    public static function getNameServerDomain(): string
    {
        return 'not.a.real.dns.provider';
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return false;
    }

    protected function accountHasDomain(array $credentials): bool
    {
        return false;
    }

    protected function addNewCredentials(): array
    {
        return [];
    }

    protected function testApiCall(array $credentials): bool
    {
        return true;
    }

    protected function getClient(array $credentials): PendingRequest
    {
        return Http::acceptJson()->asJson();
    }

    protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool
    {
        return false;
    }

    protected function getFullRecord(DnsRecordType $type, string $name): ?array
    {
        return null;
    }

    protected function getRecord(DnsRecordType $type, string $name): ?string
    {
        return null;
    }
}
