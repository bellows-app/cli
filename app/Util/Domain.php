<?php

namespace Bellows\Util;

use Illuminate\Support\Str;

class Domain
{
    public static function isBaseDomain(string $domain): string
    {
        return $domain === self::getBaseDomain($domain);
    }

    public static function getBaseDomain(string $domain): string
    {
        return Str::of($domain)->explode('.')->slice(-2)->implode('.');
    }

    public static function getSubdomain(string $domain): string
    {
        return Str::of($domain)->explode('.')->slice(0, -2)->implode('.');
    }

    public static function getFullDomain(string $subdomain, string $domain): string
    {
        $domain = self::getBaseDomain($domain);

        return ltrim("{$subdomain}.{$domain}", '.');
    }
}
