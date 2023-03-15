<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Plugin;

class Optimize extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to optimize your application');
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $this->deployScript->addBeforePHPReload($deployScript, [
            'if [ -f artisan ]; then',
            $this->artisan->inDeployScript('config:cache'),
            $this->artisan->inDeployScript('route:cache'),
            $this->artisan->inDeployScript('view:cache'),
            $this->artisan->inDeployScript('event:cache'),
            $this->artisan->inDeployScript('queue:restart'),
            'fi',
        ]);
    }
}
