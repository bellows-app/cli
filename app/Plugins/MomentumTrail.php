<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;

class MomentumTrail extends BasePlugin
{
    protected array $requiredComposerPackages = [
        'momentum-trail',
    ];

    public function updateDeployScript($server, $site, string $deployScript): string
    {
        return $this->deployScript->addAfterComposerInstall(
            $deployScript,
            $this->artisan->inDeployScript('trail:generate'),
        );
    }
}
