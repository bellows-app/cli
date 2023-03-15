<?php

namespace Bellows\Plugins;

use Bellows\Plugin;

class LaravelWebsockets extends Plugin
{
    protected array $requiredComposerPackages = [
        'beyondcode/laravel-websockets',
    ];

    public function setEnvironmentVariables(): array
    {
        return [
            'BROADCAST_DRIVER' => 'pusher',
        ];
    }
}
