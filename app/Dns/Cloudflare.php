<?php

namespace Bellows\Dns;

use Bellows\Enums\DnsRecordType;
use Bellows\PluginSdk\Facades\Console;
use Bellows\Util\Domain;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Cloudflare extends AbstractDnsProvider
{
    protected string $apiBaseUrl = 'https://api.cloudflare.com/client/v4/';

    protected string $zoneId;

    public static function getNameServerDomain(): string
    {
        return 'ns.cloudflare.com';
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(DnsRecordType::CNAME, $name, $value, $ttl);
    }

    protected function accountHasDomain(array $credentials): bool
    {
        try {
            $result = $this->getClient($credentials)->get('zones', [
                'name' => $this->baseDomain,
            ])->throw()->json();

            return count($result['result']) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function addNewCredentials(): array
    {
        Console::info('Looks like you need a Cloudflare API token. You can create one here:');
        Console::comment('https://dash.cloudflare.com/profile/api-tokens');

        $token = Console::secret('Your Cloudflare API token');

        return ['token' => $token];
    }

    protected function testApiCall(array $credentials): bool
    {
        try {
            // TODO: Also make sure we have the right scope (DNS)?
            $this->getClient($credentials)->get('user/tokens/verify')->throw();

            return true;
        } catch (Exception $e) {
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

    protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool
    {
        try {
            if ($currentRecord = $this->getFullRecord($type, $name)) {
                Console::miniTask("Updating {$type->value} record for {$name} to", $value);

                Http::dnsProvider()->put("zones/{$this->getZoneId()}/dns_records/{$currentRecord['id']}", [
                    'type'    => $type->value,
                    'name'    => $name,
                    'content' => $value,
                    'ttl'     => $ttl,
                ]);

                return true;
            }

            Console::miniTask("Adding {$type->value} record for {$name} to", $value);

            Http::dnsProvider()->post("zones/{$this->getZoneId()}/dns_records", [
                'type'    => $type->value,
                'name'    => $name,
                'content' => $value,
                'ttl'     => $ttl,
            ]);

            return true;
        } catch (Exception $e) {
            Console::error($e->getMessage());

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

        $zone = Http::dnsProvider()->get('zones', [
            'name' => $this->baseDomain,
        ])->json();

        $this->zoneId = $zone['result'][0]['id'];

        return $this->zoneId;
    }
}
