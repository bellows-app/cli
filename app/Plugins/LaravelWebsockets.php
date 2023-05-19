<?php

namespace Bellows\Plugins;

use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;

class LaravelWebsockets extends Plugin implements Launchable, Deployable
{
    protected array $requiredComposerPackages = [
        'beyondcode/laravel-websockets',
    ];

    public function launch(): void
    {
        // Nothing to do here
    }

    public function deploy(): void
    {
        // Nothing to do here
    }

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
