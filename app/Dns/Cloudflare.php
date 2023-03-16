<?php

namespace Bellows\Dns;

use Bellows\Enums\DnsRecordType;
use Bellows\Util\Domain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Cloudflare extends DnsProvider
{
    protected string $apiBaseUrl = 'https://api.cloudflare.com/client/v4/';

    protected string $zoneId;

    public static function getNameServerDomain(): string
    {
        return 'ns.cloudflare.com';
    }

    protected function accountHasDomain(array $credentials): bool
    {
        try {
            $result = $this->getClient($credentials)->get("zones", [
                'name' => $this->baseDomain,
            ])->throw()->json();

            return count($result['result']) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function addNewCredentials(): array
    {
        $this->console->info('Looks like you need a Cloudflare API token. You can create one here:');
        $this->console->comment('https://dash.cloudflare.com/profile/api-tokens');

        $token = $this->console->secret('Your Cloudflare API token');

        return compact('token');
    }

    protected function testApiCall(array $credentials): bool
    {
        try {
            // TODO: Also make sure we have the right scope (DNS)?
            $this->getClient($credentials)->get('user/tokens/verify')->throw();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getClient(array $credentials): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl)
            ->withToken($credentials['token'])
            ->acceptJson()
            ->asJson();
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(DnsRecordType::CNAME, $name, $value, $ttl);
    }

    protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool
    {
        try {
            if ($currentRecord = $this->getFullRecord($type, $name)) {
                $this->console->miniTask("Updating {$type->value} record for {$name} to", $value);

                Http::dnsProvider()->put("zones/{$this->getZoneId()}/dns_records/{$currentRecord['id']}", [
                    'type'    => $type->value,
                    'name'    => $name,
                    'content' => $value,
                    'ttl'     => $ttl,
                ]);

                return true;
            }

            $this->console->miniTask("Adding {$type->value} record for {$name} to", $value);

            Http::dnsProvider()->post("zones/{$this->getZoneId()}/dns_records", [
                'type'    => $type->value,
                'name'    => $name,
                'content' => $value,
                'ttl'     => $ttl,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->console->error($e->getMessage());
            return false;
        }
    }

    protected function getFullRecord(DnsRecordType $type, string $name): ?array
    {
        $host = Domain::getFullDomain($name === '@' ? '' : $name, $this->baseDomain);

        $records = Http::dnsProvider()->get("zones/{$this->getZoneId()}/dns_records", [
            'type' => $type->value,
            'name' => $host,
        ])->json();

        return $records['result'][0] ?? null;
    }

    protected function getRecord(DnsRecordType $type, string $name): ?string
    {
        return $this->getFullRecord($type, $name)['content'] ?? null;
    }

    protected function getZoneId()
    {
        if (isset($this->zoneId)) {
            return $this->zoneId;
        }

        $zone = Http::dnsProvider()->get("zones", [
            'name' => $this->baseDomain,
        ])->json();

        $this->zoneId = $zone['result'][0]['id'];

        return $this->zoneId;
    }
}
