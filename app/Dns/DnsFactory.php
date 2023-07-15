<?php

namespace Bellows\Dns;

use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Dns;
use Bellows\Util\Domain;

class DnsFactory
{
    public static function fromDomain(string $domain): ?AbstractDnsProvider
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
            Cloudflare::class,
        ])->first(fn ($provider) => $provider::matchByNameserver($nameserver));

        if ($result) {
            return self::confirm(app($result, ['domain' => $domain]));
        }

        Console::miniTask('Unsupported DNS provider', $domain, false);

        return app(FakeDNSProvider::class);
    }

    protected static function confirm(AbstractDnsProvider $provider): ?AbstractDnsProvider
    {
        Console::miniTask('Detected DNS provider', $provider->getName());

        if (!$provider->setCredentials()) {
            // We found a DNS provider, but they don't want to use it
            return app(FakeDNSProvider::class);
        }

        Dns::set($provider);

        return $provider;
    }
}
