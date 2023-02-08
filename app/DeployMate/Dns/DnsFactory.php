<?php

namespace App\DeployMate\Dns;

use App\DeployMate\Util\Domain;
use Illuminate\Support\Str;

class DnsFactory
{
    public static function fromDomain(string $domain): ?DnsProvider
    {
        $baseDomain = Domain::getBaseDomain($domain);
        $nameservers = dns_get_record($baseDomain, DNS_NS);

        if (count($nameservers) === 0) {
            return null;
        }

        $nameserver = $nameservers[0]['target'];

        $result = collect([
            DigitalOcean::class,
            GoDaddy::class,
        ])->first(fn ($provider) => $provider::matchByNameserver($nameserver));

        return $result ? app($result, ['domain' => $domain]) : null;
    }
}
