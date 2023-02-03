<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;

class CompileAssets extends BasePlugin
{
    public function defaultEnabled(): array
    {
        // TODO: This is a DTO
        return $this->defaultEnabledPayload(
            true,
            'You probably want to compile your assets',
        );
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
