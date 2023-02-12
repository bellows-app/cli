<?php

namespace App\Plugins;

use App\Bellows\Plugin;

class LaravelWebsockets extends Plugin
{
    protected array $requiredComposerPackages = [
        'beyondcode/laravel-websockets',
    ];

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'BROADCAST_DRIVER' => 'pusher',
        ];
    }
}