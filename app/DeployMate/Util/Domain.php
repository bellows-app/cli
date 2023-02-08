<?php

namespace App\DeployMate\Util;

use Illuminate\Support\Str;

class Domain
{
    public static function getBaseDomain(string $domain): string
    {
        return Str::of($domain)->explode('.')->slice(-2)->implode('.');
    }

    public static function getSubdomain(string $domain): string
    {
        return Str::of($domain)->explode('.')->slice(0, -2)->implode('.');
    }
}
