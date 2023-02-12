<?php

namespace App\Plugins;

use App\Bellows\Plugin;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

        $highestOctanePortInUse = collect(
            Http::forgeServer()->get('sites')->json()['sites']
        )
            ->filter(fn ($s) => $s['project_type'] === 'octane')
            ->map(fn ($s) => (string) Http::forgeServer()->get("sites/{$s['id']}/env"))
            ->map(fn ($s) => Dotenv::parse($s))
            ->map(fn ($s) => Arr::get($s,  'OCTANE_PORT'))
            ->filter()
            ->map(fn ($s) => (int) $s)
            ->max() ?: $defaultOctanePort - 1;

        $this->octanePort = $highestOctanePortInUse + 1;

        $this->octaneServer = $this->console->choice('Which server would you like to use for Octane?', [
            'roadrunner',
            'swoole',
        ], $this->env->get('OCTANE_SERVER') ?? 'swoole');
    }

    public function createSiteParams(array $params): array
    {
        return [
            'octane_port'  => $this->octanePort,
            'project_type' => 'octane',
        ];
    }

    public function setEnvironmentVariables(array $envVars): array
    {
        return  array_merge([
            'OCTANE_SERVER' => $this->octaneServer,
            'OCTANE_PORT'   => $this->octanePort,
        ], Str::startsWith($envVars['APP_URL'], 'https://') ? [
            'OCTANE_HTTPS' => 'true',
        ] : []);
    }

    public function daemons(): array
    {
        return [
            $this->artisan->forDaemon("octane:start --port={$this->octanePort} --no-interaction"),
        ];
    }
}
