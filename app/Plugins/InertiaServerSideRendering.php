<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\ForgeSite;
use Bellows\Data\PluginDaemon;
use Bellows\DeployScript;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Dotenv\Dotenv;
use Illuminate\Support\Arr;

class InertiaServerSideRendering extends Plugin implements Launchable, Deployable
{
    protected int $ssrPort;

    protected array $anyRequiredNpmPackages = [
        '@vue/server-renderer',
    ];

    public function getName(): string
    {
        return 'Inertia Server-Side Rendering';
    }

    public function launch(): void
    {
        $defaultSSRPort = 13716;

        $highestSSRPortInUse = $this->server->getSites()
            ->map(fn (ForgeSite $s) => $this->server->getSiteEnv($s->id))
            ->map(fn (string $s) => Dotenv::parse($s))
            ->map(fn (array $s) => Arr::get($s, 'SSR_PORT'))
            ->filter()
            ->map(fn ($s) => (int) $s)
            ->max() ?: $defaultSSRPort - 1;

        $this->ssrPort = $highestSSRPortInUse + 1;
    }

    public function deploy(): void
    {
    }

    public function canDeploy(): bool
    {
        // TODO: Check for deamons, deploy script
        return !$this->site->getEnv()->hasALl('SSR_PORT', 'VITE_SSR_PORT');
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
            new PluginDaemon(
                Artisan::forDaemon('inertia:start-ssr'),
            ),
        ];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addBeforePHPReload(
            $deployScript,
            Artisan::inDeployScript('inertia:stop-ssr'),
        );
    }
}
