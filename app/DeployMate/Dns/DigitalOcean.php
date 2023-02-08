<?php

namespace App\DeployMate\Dns;

use App\DeployMate\Enums\DnsRecordType;
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

        $token = $this->console->secret('Please enter your DigitalOcean API token');
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

    public function addARecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(DnsRecordType::A, $name, $value, $ttl);
    }

    public function addTXTRecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(DnsRecordType::TXT, $name, $value, $ttl);
    }

    protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool
    {
        try {
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
}
