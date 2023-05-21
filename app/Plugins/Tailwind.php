<?php

namespace Bellows\Plugins;

use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class Tailwind extends Plugin implements Installable
{
    use CanBeInstalled;

    public function install(): void
    {
    }

    public function npmPackagesToInstall(): array
    {
        return [
            '@tailwindcss/forms',
            '@tailwindcss/typography',
        ];
    }
}
