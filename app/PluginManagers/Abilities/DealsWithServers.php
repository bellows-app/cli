<?php

namespace Bellows\PluginManagers\Abilities;

use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\PluginSdk\Facades\Deployment;

trait DealsWithServers
{
    // If load balancing, the primary site, if not, the same as the $site property
    protected SiteInterface $primarySite;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerInterface $primaryServer;

    public function setPrimarySite(?SiteInterface $site): void
    {
        if ($site) {
            Deployment::setPrimarySite($site);
        }
    }

    public function setPrimaryServer(ServerInterface $server): void
    {
        Deployment::setPrimaryServer($server);
    }

    public function setSite(SiteInterface $site): void
    {
        Deployment::setSite($site);
    }

    public function setServer(ServerInterface $server): void
    {
        // TODO: Does this belong here? Or should be in the Deploy/Launch command?
        Deployment::setServer($server);
    }
}
