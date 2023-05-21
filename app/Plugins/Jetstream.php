<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Facades\Console;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Support\Facades\Process;

class Jetstream extends Plugin implements Installable
{
    use CanBeInstalled;

    protected string $stack;

    protected bool $ssr = false;

    protected bool $teams;

    public function install(): void
    {
        $this->stack = strtolower(Console::choice('Which stack would you like to use for Jetstream?', ['Inertia', 'Livewire']));

        if ($this->stack === 'inertia') {
            // TODO: Also there's some scaffolding that needs to happen here for SSR
            $this->ssr = Console::confirm('Would you like to enable server-side rendering?', false);
        }

        $this->teams = Console::confirm('Would you like to enable teams?', false);
    }

    public function composerPackagesToInstall(): array
    {
        return [
            'laravel/jetstream',
        ];
    }

    public function installWrapUp(): void
    {
        $command = 'jetstream:install ' . $this->stack;

        if ($this->teams) {
            $command .= ' --teams';
        }

        if ($this->ssr) {
            $command .= ' --ssr';
        }

        Process::run(Artisan::local($command));
    }
}
