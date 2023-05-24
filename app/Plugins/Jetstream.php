<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class Jetstream extends Plugin implements Installable
{
    use CanBeInstalled;

    public int $priority = 100;

    protected string $stack;

    protected bool $ssr = false;

    protected bool $teams;

    protected bool $darkMode;

    public function install(): void
    {
        $this->stack = strtolower(Console::choice('Which stack would you like to use for Jetstream?', ['Inertia', 'Livewire']));

        if ($this->stack === 'inertia') {
            // TODO: Do we have to deal with this: https://github.com/inertiajs/server/issues/10
            $this->ssr = Console::confirm('Would you like to enable server-side rendering?', false);
        }

        $this->teams = Console::confirm('Would you like to enable teams?', false);

        $this->darkMode = Console::confirm('Would you like to enable dark mode support?', false);
    }

    public function composerPackagesToInstall(): array
    {
        return [
            'laravel/jetstream',
        ];
    }

    public function installCommands(): array
    {
        $command = 'jetstream:install ' . $this->stack;

        if ($this->teams) {
            $command .= ' --teams';
        }

        if ($this->ssr) {
            $command .= ' --ssr';
        }

        if ($this->darkMode) {
            $command .= ' --dark';
        }

        return [
            $command,
        ];
    }

    public function installWrapUp(): void
    {
    }
}
