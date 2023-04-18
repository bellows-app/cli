<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\DeployScript;
use Bellows\PackageManagers\Npm;
use Bellows\Plugin;
use Bellows\Project;

class CompileAssets extends Plugin
{
    protected array $lockFiles = [
        'yarn.lock',
        'package-lock.json',
    ];

    public function __construct(
        protected Npm $npm,
        protected Project $project,
    ) {
    }

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        if ($this->getLockFile() === null) {
            $files = collect($this->lockFiles)->join(', ');

            return $this->disabledByDefault(
                "Could not find any of the following files: {$files}. You probably don't need to compile your assets."
            );
        }

        if (!$this->npm->hasScriptCommand('build')) {
            return $this->disabledByDefault(
                'Could not find a "build" script in your package.json. You probably don\'t need to compile your assets.'
            );
        }

        return $this->enabledByDefault('You probably want to compile your assets');
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

        return DeployScript::addAfterComposerInstall(
            $deployScript,
            $toAdd,
        );
    }

    protected function getLockFile()
    {
        return collect($this->lockFiles)->first(
            fn ($file) => file_exists($this->project->config->directory . '/' . $file)
        );
    }
}
