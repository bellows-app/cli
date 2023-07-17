<?php

namespace Bellows\Processes;

use Bellows\Contracts\ServerProviderSite;
use Bellows\Data\LaunchData;
use Bellows\PluginSdk\Data\CreateSiteParams;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Project;
use Closure;

class CreateSite
{
    public function __invoke(LaunchData $deployment, Closure $next)
    {
        Console::step('Site');

        $baseParams = new CreateSiteParams(
            domain: Project::domain(),
            projectType: 'php',
            directory: '/public',
            isolated: true,
            username: Project::isolatedUser(),
            phpVersion: Project::phpVersion()->version,
        );

        $createSiteParams = $deployment->manager->createSiteParams($baseParams->toArray());

        /** @var ServerProviderSite $site */
        $site = Console::withSpinner(
            title: 'Creating',
            task: fn () => Deployment::server()->createSite(CreateSiteParams::from($createSiteParams)),
        );

        Deployment::setSite($site);

        return $next($deployment);
    }
}
