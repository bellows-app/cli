<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Data\SecurityRule;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Closure;

class WrapUpDeployment
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        Console::step('Wrapping Up');

        collect($deployment->manager->securityRules())->each(
            fn (SecurityRule $rule) => Deployment::site()->addSecurityRule($rule)
        );

        Console::withSpinner(
            title: 'Cooling the rockets',
            task: fn () => $deployment->manager->wrapUp(),
        );

        return $next($deployment);
    }
}
