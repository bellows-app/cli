<?php

namespace App\Plugins;

use App\DeployMate\DefaultEnabledDecision;
use App\DeployMate\Plugin;

class CompileAssets extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to compile your assets');
    }

    public function updateDeployScript($server, $site, string $deployScript): string
    {
        return $this->deployScript->addAfterComposerInstall(
            $deployScript,
            [
                'yarn',
                'yarn build',
            ],
        );
    }
}
