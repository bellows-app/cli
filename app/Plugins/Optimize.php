<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\DefaultEnabledDecision;
use Bellows\DeployScript;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeLaunched;

class Optimize extends Plugin implements Launchable, Deployable
{
    use CanBeLaunched;

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to optimize your application');
    }

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->isInDeploymentScript([
            'config:cache',
            'route:cache',
            'view:cache',
            'event:cache',
        ]);
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
