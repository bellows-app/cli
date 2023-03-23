<?php

namespace Bellows\Dns;

use Bellows\Enums\DnsRecordType;
use Bellows\Util\Domain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DigitalOcean extends DnsProvider
{
    protected string $apiBaseUrl = 'https://api.digitalocean.com/v2/';

    public static function getNameServerDomain(): string
    {
        return 'digitalocean.com';
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(
            DnsRecordType::CNAME,
            $name,
            $this->withTrailingDot($value),
            $ttl,
        );
    }

    protected function accountHasDomain(array $credentials): bool
    {
        try {
            $this->getClient($credentials)->get("domains/{$this->baseDomain}")->throw();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function addNewCredentials(): array
    {
        $this->console->info('Looks like you need a DigitalOcean API token. You can create one here:');
        $this->console->comment('https://cloud.digitalocean.com/account/api/tokens');

        $token = $this->console->secret('Your DigitalOcean API token');

        return compact('token');
    }

    protected function testApiCall(array $credentials): bool
    {
        try {
            $this->getClient($credentials)->get('account')->throw();

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

    protected function getDefaultNewAccountName(array $credentials): ?string
    {
        $result = $this->getClient($credentials)->get('account')->json();

        $teamName = Arr::get($result, 'account.team.name');

        return $teamName === 'My Team' ? null : Str::slug($teamName);
    }

    protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool
    {
        try {
            if ($currentRecord = $this->getFullRecord($type, $name)) {
                $this->console->miniTask("Updating {$type->value} record for {$name} to", $value);

                Http::dnsProvider()->put("domains/{$this->baseDomain}/records/{$currentRecord['id']}", [
                    'type' => $type->value,
                    'name' => $name,
                    'data' => $value,
                    'ttl'  => $ttl,
                ]);

                return true;
            }

            $this->console->miniTask("Adding {$type->value} record for {$name} to", $value);

            Http::dnsProvider()->post("domains/{$this->baseDomain}/records", [
                'type' => $type->value,
                'name' => $name,
                'data' => $value,
                'ttl'  => $ttl,
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

        $records = Http::dnsProvider()->get("domains/{$this->baseDomain}/records", [
            'type' => $type->value,
            'name' => $host,
        ])->json();

        return $records['domain_records'][0] ?? null;
    }

    protected function getRecord(DnsRecordType $type, string $name): ?string
    {
        return $this->getFullRecord($type, $name)['data'] ?? null;
    }
}
