<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\DeployScript;
use Closure;

class UpdateDeploymentScript
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        $deployScript = $deployment->site->getDeploymentScript();
        DeployScript::set($deployScript);

        // $updatedDeployScript = $deployment->manager->updateDeployScript($deployScript);

        $deployment->manager->updateDeployScript();

        $updatedDeployScript = DeployScript::get();

        if ($updatedDeployScript === $deployScript) {
            return $next($deployment);
        }

        Console::step('Deploy Script');

        Console::withSpinner(
            title: 'Updating',
            task: fn () => $deployment->site->updateDeploymentScript($updatedDeployScript),
        );

        $deployment->summary[] = ['Deploy Script', $updatedDeployScript];

        return $next($deployment);
    }
}
