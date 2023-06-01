<?php

namespace Bellows\PluginManagers;

use Bellows\PluginSdk\Plugin;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

trait DealsWithServers
{
    // If load balancing, the primary site, if not, the same as the $site property
    protected SiteInterface $primarySite;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerInterface $primaryServer;

    public function setPrimarySite(?SiteInterface $site): void
    {
        if ($site) {
            $this->primarySite = $site;
        }
    }

    public function setPrimaryServer(ServerInterface $server): void
    {
        $this->primaryServer = $server;
    }

    public function setSite(SiteInterface $site): void
    {
        $this->call('setSite')->withArgs($site)->run();
    }

    public function setServer(ServerInterface $server): void
    {
        $this->call('setServer')->withArgs($server)->run();
    }

    protected function getAllPluginsWithSiteAndServer(): Collection
    {
        return $this->getAllPlugins()
            ->each(fn (Plugin $p) => isset($this->primarySite) ? $p->setPrimarySite($this->primarySite) : null)
            ->each(fn (Plugin $p) => $p->setPrimaryServer($this->primaryServer));
    }
}
