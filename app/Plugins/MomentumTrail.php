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

    public function __construct(
        protected Artisan $artisan,
    ) {
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addAfterComposerInstall(
            $deployScript,
            $this->artisan->inDeployScript('trail:generate'),
        );
    }
}
