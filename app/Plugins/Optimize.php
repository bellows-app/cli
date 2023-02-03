<?php

namespace App\Plugins;

use App\DeployMate\Plugin;

class Optimize extends Plugin
{
    public function defaultEnabled(): array
    {
        return $this->defaultEnabledPayload(
            true,
            'You probably want to optimize your application',
        );
    }

    public function updateDeployScript(
        $server,
        $site,
        string $deployScript
    ): string {
        return $this->deployScript->addBeforePHPReload($deployScript, [
            $this->artisan->inDeployScript('config:cache'),
            $this->artisan->inDeployScript('route:cache'),
            $this->artisan->inDeployScript('view:cache'),
            $this->artisan->inDeployScript('event:cache'),
            $this->artisan->inDeployScript('queue:restart'),
        ]);
    }
}
