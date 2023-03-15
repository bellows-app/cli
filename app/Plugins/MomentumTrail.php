<?php

namespace Bellows\Plugins;

use Bellows\Plugin;

class MomentumTrail extends Plugin
{
    protected array $requiredComposerPackages = [
        'based/momentum-trail',
    ];

    public function updateDeployScript(string $deployScript): string
    {
        return $this->deployScript->addAfterComposerInstall(
            $deployScript,
            $this->artisan->inDeployScript('trail:generate'),
        );
    }
}
