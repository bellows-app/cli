<?php

namespace App\Bellows\Dns;

use App\Bellows\Enums\DnsRecordType;
use App\Bellows\Util\Domain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// https://developers.cloudflare.com/api/operations/dns-records-for-a-zone-list-dns-records
// ipghost.app

class Cloudflare extends DnsProvider
{
    protected string $apiBaseUrl = 'https://api.digitalocean.com/v2/';

    public static function getNameServerDomain(): string
    {
        return 'ns.cloudflare.com';
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

    protected function addNewCredentials(): void
    {
        $this->console->info('https://cloud.digitalocean.com/account/api/tokens');

        $token = $this->console->secret('Your DigitalOcean API token');
        $name = $this->console->ask('Name', $this->getDefaultNewAccountName($token));

        $this->setConfig($name, compact('token'));
    }

    protected function getClient(array $credentials): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl)
            ->withToken($credentials['token'])
            ->acceptJson()
            ->asJson();
    }

    protected function getDefaultNewAccountName(string $token): ?string
    {
        $result = $this->getClient(compact('token'))->get('account')->json();

        $teamName = Arr::get($result, 'account.team.name');

        return $teamName === 'My Team' ? null : Str::slug($teamName);
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(
            DnsRecordType::CNAME,
            $name,
            // Requires a trailing dot
            Str::of($value)->trim('.')->wrap('', '.')->toString(),
            $ttl,
        );
    }

    protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool
    {
        try {
            if ($currentRecord = $this->getFullRecord($type, $name)) {
                $this->console->info("Updating {$type->value} record for {$name} to {$value}");

                Http::dnsProvider()->put("domains/{$this->baseDomain}/records/{$currentRecord['id']}", [
                    'type' => $type->value,
                    'name' => $name,
                    'data' => $value,
                    'ttl'  => $ttl,
                ]);

                return true;
            }

            $this->console->info("Adding {$type->value} record for {$name} to {$value}");

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
