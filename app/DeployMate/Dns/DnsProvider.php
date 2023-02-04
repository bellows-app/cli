<?php

namespace App\DeployMate\Dns;

use App\DeployMate\Config;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class DnsProvider
{
    use InteractsWithIO;

    protected $baseDomain;

    public function __construct(
        protected Config $config,
        protected string $domain,
    ) {
        $this->baseDomain = Str::of($domain)->explode('.')->slice(-2)->implode('.');
    }

    abstract public static function getNameServerDomain(): string;

    abstract public function setCredentials(): void;

    abstract public function addCNAMERecord(string $name, string $value, int $ttl): bool;

    abstract public function addARecord(string $name, string $value, int $ttl): bool;

    abstract public function addTXTRecord(string $name, string $value, int $ttl): bool;

    public function getName()
    {
        return class_basename($this);
    }

    public static function matchByNameserver(string $nameserver): bool
    {
        return Str::contains($nameserver, static::getNameServerDomain());
    }

    protected function getConfig()
    {
        return $this->config->get('dns.' . $this->getName());
    }

    protected function setConfig(string $name, string|array $payload)
    {
        $this->config->set('dns.' . $this->getName() . '.' . $name, $payload);
    }

    protected function setClient($cb)
    {
        Http::macro('dns', $cb);
    }
}
