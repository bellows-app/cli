<?php

namespace App\DeployMate\Dns;

use App\DeployMate\Enums\DnsRecordType;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoDaddy extends DnsProvider
{
    protected string $apiBaseUrl = 'https://api.godaddy.com/v1/';

    public static function getNameServerDomain(): string
    {
        return 'domaincontrol.com';
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
        $this->console->info('https://developer.godaddy.com/keys');
        $key = $this->console->secret('Please enter your GoDaddy key');
        $secret = $this->console->secret('Please enter your GoDaddy secret');
        $name = $this->console->ask('Name');

        $this->setConfig($name, compact('key', 'secret'));
    }

    protected function getClient(array $credentials): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl)
            ->withToken("{$credentials['key']}:{$credentials['secret']}", 'sso-key')
            ->acceptJson()
            ->asJson();
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
            Http::dnsProvider()->patch("domains/{$this->baseDomain}/records", [
                [
                    "data" => $value,
                    "name" => $name,
                    "ttl"  => $ttl,
                    "type" => $type->value,
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            $this->console->error($e->getMessage());
            return false;
        }
    }
}
