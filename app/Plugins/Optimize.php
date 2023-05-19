<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\DefaultEnabledDecision;
use Bellows\DeployScript;
use Bellows\Plugin;

class Optimize extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to optimize your application');
    }

    public function canDeploy(): bool
    {
        // TODO: Check deploy script
        return false;
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addBeforePHPReload($deployScript, [
            Artisan::inDeployScript('config:cache'),
            Artisan::inDeployScript('route:cache'),
            Artisan::inDeployScript('view:cache'),
            Artisan::inDeployScript('event:cache'),
        ]);
    }
}
