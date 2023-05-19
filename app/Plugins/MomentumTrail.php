<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\DeployScript;
use Bellows\Plugin;

class MomentumTrail extends Plugin
{
    protected array $requiredComposerPackages = [
        'based/momentum-trail',
    ];

    public function canDeploy(): bool
    {
        // TODO: Check for deploy script
        return false;
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
