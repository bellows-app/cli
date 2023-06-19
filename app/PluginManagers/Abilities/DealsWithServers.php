<?php

namespace Bellows\PluginManagers\Abilities;

use Bellows\Contracts\ServerProviderServer;
use Bellows\Contracts\ServerProviderSite;
use Bellows\PluginSdk\Facades\Deployment;

trait DealsWithServers
{
    // If load balancing, the primary site, if not, the same as the $site property
    protected ServerProviderSite $primarySite;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerProviderServer $primaryServer;

    public function setPrimarySite(?ServerProviderSite $site): void
    {
        if ($site) {
            Deployment::setPrimarySite($site);
        }
    }

    public function setPrimaryServer(ServerProviderServer $server): void
    {
        Deployment::setPrimaryServer($server);
    }

    public function setSite(ServerProviderSite $site): void
    {
        Deployment::setSite($site);
    }

    public function setServer(ServerProviderServer $server): void
    {
        // TODO: Does this belong here? Or should be in the Deploy/Launch command?
        Deployment::setServer($server);
    }
}
