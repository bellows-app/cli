<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Dns;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Util\Domain;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
