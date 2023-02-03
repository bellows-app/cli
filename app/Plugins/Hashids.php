<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;
use Illuminate\Support\Str;

class Hashids extends BasePlugin
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
