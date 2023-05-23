<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\DeployScript;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Bellows\Plugins\Helpers\CanBeLaunched;
use Bellows\Util\Vite;

class MomentumTrail extends Plugin implements Launchable, Deployable, Installable
{
    use CanBeInstalled, CanBeLaunched;

    protected array $requiredComposerPackages = [
        'based/momentum-trail',
    ];

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->isInDeploymentScript('trail:generate');
    }

    public function npmPackagesToInstall(): array
    {
        return ['momentum-trail'];
    }

    public function npmDevPackagesToInstall(): array
    {
        return ['vite-plugin-watch'];
    }

    public function installWrapUp(): void
    {
        // TODO: Also update entry point file (app.js)
        Vite::addImport("import { watch } from 'vite-plugin-watch'");
        Vite::addPlugin(<<<'PLUGIN'
watch({
    pattern: 'routes/*.php',
    command: 'php artisan trail:generate',
})
PLUGIN);
    }

    public function updateConfig(): array
    {
        return [
            'trail.output.routes'     => "resource_path('js/routes.json')",
            'trail.output.typescript' => "resource_path('types/routes.d.ts')",
        ];
    }

    public function publishTags(): array
    {
        return ['trail-config'];
    }

    public function updateDeployScript(string $deployScript): string
    {
        // TODO: Probably add a check to see if the routes.json file exists and touch it if it doesn't
        // (based on the config file, either the published one or the vendor one)
        return DeployScript::addAfterComposerInstall(
            $deployScript,
            Artisan::inDeployScript('trail:generate'),
        );
    }
}
