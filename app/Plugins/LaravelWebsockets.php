<?php

namespace Bellows\Plugins;

use Bellows\Plugin;

class LaravelWebsockets extends Plugin
{
    protected array $requiredComposerPackages = [
        'beyondcode/laravel-websockets',
    ];

    public function canDeploy(): bool
    {
        return $this->site->getEnv()->get('BROADCAST_DRIVER') !== 'pusher';
    }

    public function environmentVariables(): array
    {
        return [
            'BROADCAST_DRIVER' => 'pusher',
        ];
    }
}
