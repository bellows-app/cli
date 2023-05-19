<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\DeployScript;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;

class MomentumTrail extends Plugin implements Launchable, Deployable
{
    protected array $requiredComposerPackages = [
        'based/momentum-trail',
    ];

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
        return !$this->site->isInDeploymentScript('trail:generate');
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
