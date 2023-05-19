<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\CreateSiteParams;
use Bellows\Data\ForgeSite;
use Bellows\Data\PluginDaemon;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Dotenv\Dotenv;
use Illuminate\Support\Arr;

class Octane extends Plugin
{
    protected int $octanePort;

    protected string $octaneServer;

    protected array $requiredComposerPackages = [
        'laravel/octane',
    ];

    public function setup(): void
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

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->hasAll('OCTANE_PORT', 'OCTANE_SERVER');
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
        return  array_merge([
            'OCTANE_SERVER' => $this->octaneServer,
            'OCTANE_PORT'   => $this->octanePort,
        ], Project::config()->secureSite ? [
            'OCTANE_HTTPS' => true,
        ] : []);
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
