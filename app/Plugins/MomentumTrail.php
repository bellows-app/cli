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

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addAfterComposerInstall(
            $deployScript,
            Artisan::inDeployScript('trail:generate'),
        );
    }
}
