<?php

namespace App\DeployMate\Dns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DigitalOcean extends DnsProvider
{
    protected string $nameserverDomain = 'digitalocean.com';

    public function setCredentials(): void
    {
        $config = $this->getConfig();

        if (!$config) {
            $this->askForToken();
            return;
        }

        $token = collect($config)->first(fn ($token) => $this->checkAccountForDomain($token));

        if (!$token) {
            $this->line('No account found for this domain.');

            if (!$this->confirm('Do you want to add a new account?', true)) {
                return;
            }

            $this->askForToken();
            return;
        }

        $this->line('Found account for this domain: ' . collect($config)->search($token));

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
        $this->info('https://cloud.digitalocean.com/account/api/tokens');
        $token = $this->secret('Please enter your DigitalOcean API token');
        $name = $this->ask('Name', $this->getDefaultNewAccountName($token));

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
            'CNAME',
            $name,
            Str::of($value)->trim('.')->wrap('', '.')->toString(), // DigitalOcean requires a trailing dot
            $ttl,
        );
    }

    public function addARecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord('A', $name, $value, $ttl);
    }

    public function addTXTRecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord('TXT', $name, $value, $ttl);
    }

    protected function addRecord(string $type, string $name, string $value, int $ttl): bool
    {
        try {
            Http::dns()->post("domains/{$this->baseDomain}/records", [
                'type' => $type,
                'name' => $name,
                'data' => $value,
                'ttl'  => $ttl,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }
}
