<?php

namespace App\Plugins;

use App\Bellows\Data\DefaultEnabledDecision;
use App\Bellows\Plugin;

class CompileAssets extends Plugin
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

        if (!$this->npm->hasScriptCommand('build')) {
            return $this->disabledByDefault(
                'Could not find a "build" script in your package.json. You probably don\'t need to compile your assets.'
            );
        }

        return $this->enabledByDefault('You probably want to compile your assets');
    }

    protected function getLockFile()
    {
        return collect($this->lockFiles)->first(fn ($file) => file_exists($this->projectConfig->projectDirectory . '/' . $file));
    }

    public function updateDeployScript(string $deployScript): string
    {
        $lockFile = $this->getLockFile();

        if ($lockFile === 'yarn.lock') {
            return $this->deployScript->addAfterComposerInstall(
                $deployScript,
                [
                    'yarn',
                    'yarn build',
                ],
            );
        }

        return $this->deployScript->addAfterComposerInstall(
            $deployScript,
            [
                'npm install',
                'npm run build',
            ],
        );
    }
}
