<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\CreateSiteParams;
use Bellows\Data\ForgeSite;
use Bellows\Data\PluginDaemon;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Dotenv\Dotenv;
use Illuminate\Support\Arr;

class Octane extends Plugin implements Launchable, Deployable
{
    protected int $octanePort;

    protected string $octaneServer;

    protected array $requiredComposerPackages = [
        'laravel/octane',
    ];

    public function launch(): void
    {
        $defaultOctanePort = 8000;

        $highestOctanePortInUse = $this->server->getSites()
            ->filter(fn (ForgeSite $s) => $s->project_type === 'octane')
            ->map(fn (ForgeSite $s) => $this->server->getSiteEnv($s->id))
            ->map(fn (string $s) => Dotenv::parse($s))
            ->map(fn (array $s) => Arr::get($s, 'OCTANE_PORT'))
            ->filter()
            ->map(fn ($s) => (int) $s)
            ->max() ?: $defaultOctanePort - 1;

        $this->octanePort = $highestOctanePortInUse + 1;

        $this->octaneServer = Console::choice('Which server would you like to use for Octane?', [
            'roadrunner',
            'swoole',
        ], Project::env()->get('OCTANE_SERVER') ?? 'swoole');
    }

    public function deploy(): bool
    {
        $this->launch();

        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->hasAll('OCTANE_PORT', 'OCTANE_SERVER')
            || !$this->server->getDaemons()->contains(
                fn ($daemon) => str_contains($daemon['command'], Artisan::forDaemon('octane:start'))
            );
    }

    public function createSiteParams(CreateSiteParams $params): array
    {
        return [
            'octane_port'  => $this->octanePort,
            'project_type' => 'octane',
        ];
    }

    public function environmentVariables(): array
    {
        $vars = [
            'OCTANE_SERVER' => $this->octaneServer,
            'OCTANE_PORT'   => $this->octanePort,
        ];

        if (Project::config()->secureSite) {
            $vars['OCTANE_HTTPS'] = true;
        }

        return $vars;
    }

    public function daemons(): array
    {
        return [
            new PluginDaemon(
                Artisan::forDaemon("octane:start --port={$this->octanePort} --no-interaction"),
            ),
        ];
    }
}
