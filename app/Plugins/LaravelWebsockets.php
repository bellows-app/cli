<?php

namespace Bellows\Plugins;

use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeLaunched;
use Bellows\Util\DeployHelper;

class LaravelWebsockets extends Plugin implements Launchable, Deployable
{
    use CanBeLaunched;

    protected const BROADCAST_DRIVER = 'pusher';

    protected array $requiredComposerPackages = [
        'beyondcode/laravel-websockets',
    ];

    public function deploy(): bool
    {
        if (
            !DeployHelper::wantsToChangeValueTo(
                $this->site->getEnv()->get('BROADCAST_DRIVER'),
                self::BROADCAST_DRIVER,
                'Change broadcast driver to Laravel Websockets'
            )
        ) {
            return false;
        }

        $this->launch();

        return true;
    }

    public function canDeploy(): bool
    {
        return $this->site->getEnv()->get('BROADCAST_DRIVER') !== self::BROADCAST_DRIVER;
    }

    public function environmentVariables(): array
    {
        return [
            'BROADCAST_DRIVER' => self::BROADCAST_DRIVER,
        ];
    }
}
