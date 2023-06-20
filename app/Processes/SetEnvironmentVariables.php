<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Closure;

class SetEnvironmentVariables
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        $updatedVars = collect($deployment->manager->environmentVariables());

        if ($updatedVars->isEmpty()) {
            return $next($deployment);
        }

        Console::step('Environment Variables');

        $siteEnv = Deployment::site()->env();

        $updatedVars->each(
            fn ($v, $k) => $siteEnv->set(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        Console::withSpinner(
            title: 'Updating',
            task: fn () => Deployment::site()->updateEnv((string) $siteEnv),
        );

        $deployment->summary[] = ['Environment Variables', $updatedVars->keys()->join(PHP_EOL)];

        return $next($deployment);
    }
}
