<?php

namespace Bellows\Processes;

use Bellows\Data\LaunchData;
use Bellows\PluginSdk\Data\InstallRepoParams;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Project;
use Closure;

class InstallRepository
{
    public function __invoke(LaunchData $deployment, Closure $next)
    {
        $baseRepoParams = new InstallRepoParams(
            // TODO: Make this configurable
            provider: Project::repo()->provider->value,
            repository: Project::repo()->url,
            branch: Project::repo()->branch,
            composer: true,
        );

        $installRepoParams = $deployment->manager->installRepoParams($baseRepoParams->toArray());

        Console::step('Repository');

        Console::withSpinner(
            title: 'Installing',
            task: fn () => Deployment::site()->installRepo(InstallRepoParams::from($installRepoParams)),
        );

        return $next($deployment);
    }
}
