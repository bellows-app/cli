<?php

namespace Bellows\Plugins;

use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class Ray extends Plugin implements Installable
{
    use CanBeInstalled;

    protected string $connection;

    protected string $database;

    protected string $username;

    protected string $password;

    public function composerPackagesToInstall(): array
    {
        return [
            ['spatie/laravel-ray', true],
        ];
    }
}
