<?php

namespace App\DeployMate\Dns;

use App\DeployMate\Enums\DnsRecordType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoDaddy extends DnsProvider
{
    public static function getNameServerDomain(): string
    {
        return 'domaincontrol.com';
    }

    public function setCredentials(): void
    {
        $config = $this->getConfig();

        if (!$config) {
            $this->askForToken();
            return;
        }

        $credentials = collect($config)->first(fn ($credentials) => $this->checkAccountForDomain($credentials['key'], $credentials['secret']));

        if (!$credentials) {
            $this->console->line('No account found for this domain.');

            if (!$this->console->confirm('Do you want to add a new account?', true)) {
                return;
            }

            $this->askForToken();
            return;
        }

        $this->console->line('Found account for this domain: ' . collect($config)->search($credentials));

        $this->setClient(fn () => $this->getClient($credentials['key'], $credentials['secret']));
    }

    protected function checkAccountForDomain(string $key, string $secret): bool
    {
        try {
            $this->getClient($key, $secret)->get("domains/{$this->baseDomain}")->throw();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function askForToken()
    {
        $this->console->info('https://developer.godaddy.com/keys');
        $key = $this->console->secret('Please enter your GoDaddy key');
        $secret = $this->console->secret('Please enter your GoDaddy secret');
        $name = $this->console->ask('Name');

        $this->setConfig($name, compact('key', 'secret'));

        return $this->setCredentials();
    }

    protected function getClient(string $key, string $secret)
    {
        return Http::baseUrl('https://api.godaddy.com/v1/')
            ->withToken("{$key}:{$secret}", 'sso-key')
            ->acceptJson()
            ->asJson();
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
            Http::dns()->patch("domains/{$this->baseDomain}/records", [
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
