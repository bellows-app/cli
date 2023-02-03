<?php

namespace App\Plugins;

use App\DeployMate\Plugin;
use Illuminate\Support\Str;

class Hashids extends Plugin
{
    protected array $anyRequiredComposerPackages = [
        'mtvs/eloquent-hashids',
        'vinkla/hashids',
    ];

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'HASH_IDS_SALT' => Str::random(16),
        ];
    }
}
