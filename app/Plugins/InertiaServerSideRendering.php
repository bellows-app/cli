<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class InertiaServerSideRendering extends BasePlugin
{
    protected int $ssrPort;

    protected array $anyRequiredNpmPackages = [
        '@vue/server-renderer',
    ];

    public function setup($server): void
    {
        $defaultSSRPort = 13716;

        $highestSSRPortInUse = collect(
            Http::forge()->get("servers/{$server['id']}/sites")->json()['sites']
        )
            ->map(fn ($s) => (string) Http::forge()->get("servers/{$server['id']}/sites/{$s['id']}/env"))
            ->map(fn ($s) => Dotenv::parse($s))
            ->map(fn ($s) => Arr::get($s,  'SSR_PORT'))
            ->filter()
            ->map(fn ($s) => (int) $s)
            ->max() ?: $defaultSSRPort - 1;

        $this->ssrPort = $highestSSRPortInUse + 1;
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return  [
            'SSR_PORT'      => $this->ssrPort,
            'VITE_SSR_PORT' => '${SSR_PORT}',
        ];
    }

    public function daemons($server, $site): array
    {
        return [
            $this->artisan->forDaemon('inertia:start-ssr'),
        ];
    }

    public function updateDeployScript($server, $site, string $deployScript): string
    {
        return $this->deployScript->addAfterLine(
            $deployScript,
            $this->artisan->inDeployScript('event:cache'),
            $this->artisan->inDeployScript('inertia:start-ssr'),
        );
    }
}
