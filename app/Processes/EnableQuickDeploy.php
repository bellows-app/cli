<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Closure;

class EnableQuickDeploy
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        if (Console::confirm('Enable Quick Deploy?')) {
            Deployment::site()->enableQuickDeploy();
        }

        return $next($deployment);
    }
}
