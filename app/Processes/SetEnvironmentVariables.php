<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
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

        $siteEnv = $deployment->site->env();

        $updatedVars->each(
            fn ($v, $k) => $siteEnv->set(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        Console::withSpinner(
            title: 'Updating',
            task: fn () => $deployment->site->updateEnv((string) $siteEnv),
        );

        $deployment->summary[] = ['Environment Variables', $updatedVars->keys()->join(PHP_EOL)];

        return $next($deployment);
    }
}
