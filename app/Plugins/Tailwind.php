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
        // TODO: More here probably. Or maybe this is just a file they copy?
        return [
            '@tailwindcss/forms',
            '@tailwindcss/typography',
        ];
    }
}
