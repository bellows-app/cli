<?php

namespace Bellows\Plugins;

use Bellows\Data\Daemon;
use Bellows\Plugin;
use Dotenv\Dotenv;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class InertiaServerSideRendering extends Plugin
{
    protected int $ssrPort;

    protected array $anyRequiredNpmPackages = [
        '@vue/server-renderer',
    ];

    public function setup(): void
    {
        $defaultSSRPort = 13716;

        $highestSSRPortInUse = collect(
            Http::forgeServer()->get('sites')->json()['sites']
        )
            ->map(fn ($s) => (string) Http::forgeServer()->get("sites/{$s['id']}/env"))
            ->map(fn ($s) => Dotenv::parse($s))
            ->map(fn ($s) => Arr::get($s, 'SSR_PORT'))
            ->filter()
            ->map(fn ($s) => (int) $s)
            ->max() ?: $defaultSSRPort - 1;

        $this->ssrPort = $highestSSRPortInUse + 1;
    }

    public function environmentVariables(): array
    {
        return  [
            'SSR_PORT'      => $this->ssrPort,
            'VITE_SSR_PORT' => '${SSR_PORT}',
        ];
    }

    public function daemons(): array
    {
        return [
            new Daemon(
                $this->artisan->forDaemon('inertia:start-ssr'),
            ),
        ];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $this->deployScript->addBeforePHPReload(
            $deployScript,
            $this->artisan->inDeployScript('inertia:stop-ssr'),
        );
    }
}
