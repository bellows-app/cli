<?php

namespace App\Plugins;

use App\Bellows\Plugin;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class Octane extends Plugin
{
    protected int $octanePort;

    protected array $requiredComposerPackages = [
        'laravel/octane',
    ];

    public function setup($server): void
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
    }

    public function createSiteParams(array $params): array
    {
        return [
            'octane_port'  => $this->octanePort,
            'project_type' => 'octane',
        ];
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return  [
            'OCTANE_SERVER' => 'swoole',
            'OCTANE_HTTPS'  => 'true',
            'OCTANE_PORT'   => $this->octanePort,
        ];
    }

    public function daemons($server, $site): array
    {
        return [
            $this->artisan->forDaemon("octane:start --port={$this->octanePort} --no-interaction"),
        ];
    }
}
