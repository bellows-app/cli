<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\DeployScript;
use Bellows\PackageManagers\Npm;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;

class CompileAssets extends Plugin implements Launchable, Deployable
{
    protected $yarnLines = [
        'yarn',
        'yarn build',
    ];

    protected $npmLines = [
        'npm install',
        'npm run build',
    ];

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        if (Npm::getPackageManager() === null) {
            return $this->disabledByDefault(
                "Could not find any npm or yarn lock files. You probably don't need to compile your assets."
            );
        }

        if (!Npm::hasScriptCommand('build')) {
            return $this->disabledByDefault(
                'Could not find a "build" script in your package.json. You probably don\'t need to compile your assets.'
            );
        }

        return $this->enabledByDefault('You probably want to compile your assets');
    }

    public function launch(): void
    {
        // Nothing to do here
    }

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->isInDeploymentScript($this->yarnLines) && !$this->site->isInDeploymentScript($this->npmLines);
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addAfterComposerInstall(
            $deployScript,
            match (Npm::getPackageManager()) {
                'yarn'  => $this->yarnLines,
                default => $this->npmLines,
            },
        );
    }
}
