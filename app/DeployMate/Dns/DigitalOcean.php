<?php

namespace App\DeployMate\Dns;

use App\DeployMate\Enums\DnsRecordType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DigitalOcean extends DnsProvider
{
    public static function getNameServerDomain(): string
    {
        return 'digitalocean.com';
    }

    public function setCredentials(): void
    {
        $config = $this->getConfig();

        if (!$config) {
            $this->askForToken();
            return;
        }

        $token = collect($config)->first(fn ($token) => $this->checkAccountForDomain($token));

        if (!$token) {
            $this->console->line('No account found for this domain.');

            if (!$this->console->confirm('Do you want to add a new account?', true)) {
                return;
            }

            $this->askForToken();
            return;
        }

        $this->console->line('Found account for this domain: ' . collect($config)->search($token));

        $this->setClient(fn () => $this->getClient($token));
    }

    protected function checkAccountForDomain(string $token): bool
    {
        try {
            $this->getClient($token)->get("domains/{$this->baseDomain}")->throw();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function askForToken()
    {
        $this->console->info('https://cloud.digitalocean.com/account/api/tokens');
        $token = $this->console->secret('Please enter your DigitalOcean API token');
        $name = $this->console->ask('Name', $this->getDefaultNewAccountName($token));

        $this->setConfig($name, $token);

        return $this->setCredentials();
    }

    protected function getClient(string $token)
    {
        return Http::baseUrl('https://api.digitalocean.com/v2/')
            ->withToken($token)
            ->acceptJson()
            ->asJson();
    }

    protected function getDefaultNewAccountName(string $token): ?string
    {
        $result = $this->getClient($token)->get('account')->json();

        $teamName = Arr::get($result, 'account.team.name');

        return $teamName === 'My Team' ? null : $teamName;
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(
            DnsRecordType::CNAME,
            $name,
            Str::of($value)->trim('.')->wrap('', '.')->toString(), // DigitalOcean requires a trailing dot
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
            Http::dns()->post("domains/{$this->baseDomain}/records", [
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
