<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\DefaultEnabledDecision;
use Bellows\DeployScript;
use Bellows\Plugin;

class Optimize extends Plugin
{
    public function __construct(
        protected Artisan $artisan,
    ) {
    }

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to optimize your application');
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addBeforePHPReload($deployScript, [
            $this->artisan->inDeployScript('config:cache'),
            $this->artisan->inDeployScript('route:cache'),
            $this->artisan->inDeployScript('view:cache'),
            $this->artisan->inDeployScript('event:cache'),
        ]);
    }
}
