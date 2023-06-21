<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Dns;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Util\Domain;
use Closure;

class SetupSSL
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        if (!Project::siteIsSecure() || !Dns::available()) {
            return $next($deployment);
        }

        Console::step('Creating SSL Certificate');

        $domains = [Project::domain()];

        if (Domain::isBaseDomain(Project::domain())) {
            $domains[] = 'www.' . Project::domain();
        }

        Deployment::primarySite()->createSslCertificate($domains);

        return $next($deployment);
    }
}
