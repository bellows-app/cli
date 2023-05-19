<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\DeployScript;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Npm;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;

class CompileAssets extends Plugin implements Launchable, Deployable
{
    protected array $lockFiles = [
        'yarn.lock',
        'package-lock.json',
    ];

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        if ($this->getLockFile() === null) {
            $files = collect($this->lockFiles)->join(', ');

            return $this->disabledByDefault(
                "Could not find any of the following files: {$files}. You probably don't need to compile your assets."
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

    public function deploy(): void
    {
        // Nothing to do here
    }

    public function canDeploy(): bool
    {
        // TODO: Check if the build script is already in the deploy script
        return false;
    }

    public function updateDeployScript(string $deployScript): string
    {
        $toAdd = $this->getLockFile() === 'yarn.lock' ? [
            'yarn',
            'yarn build',
        ] : [
            'npm install',
            'npm run build',
        ];

        return DeployScript::addAfterComposerInstall($deployScript, $toAdd);
    }

    protected function getLockFile()
    {
        return collect($this->lockFiles)->first(
            fn ($file) => file_exists(Project::config()->directory . '/' . $file)
        );
    }
}
