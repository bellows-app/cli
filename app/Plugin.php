<?php

namespace Bellows;

use Bellows\Data\PluginDaemon;
use Bellows\Data\PluginJob;
use Bellows\Data\PluginWorker;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;

abstract class Plugin
{
    use InteractsWithConfig;
    use MakesEnabledDecisions;

    public int $priority = 0;

    // protected Env $localEnv;
    // The site we're currently deploying to
    protected SiteInterface $site;

    // If load balancing, the primary site, if not, the same as the $site property
    protected SiteInterface $loadBalancingSite;

    // The server we're currently deploying to
    protected ServerInterface $server;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerInterface $loadBalancingServer;

    public function setSite(SiteInterface $site): self
    {
        $this->site = $site;

        if (!isset($this->loadBalancingSite)) {
            // If we don't have a primary site at this point, also set this as the primary site
            // TODO: Is this an ugly/unexpected side effect? Oof.
            $this->loadBalancingSite = $site;
        }

        return $this;
    }

    public function setLoadBalancingSite(SiteInterface $site): self
    {
        $this->loadBalancingSite = $site;

        return $this;
    }

    public function setServer(ServerInterface $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function setLoadBalancingServer(ServerInterface $primaryServer): self
    {
        $this->loadBalancingServer = $primaryServer;

        return $this;
    }

    public function setup(): void
    {
        //
    }

    public function createSiteParams(array $params): array
    {
        return [];
    }

    public function installRepoParams(array $baseParams): array
    {
        return [];
    }

    public function environmentVariables(): array
    {
        return [];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $deployScript;
    }

    /**
     * @return PluginWorker[]
     */
    public function workers(): array
    {
        return [];
    }

    /**
     * @return PluginJob[]
     */
    public function jobs(): array
    {
        return [];
    }

    /**
     * @return PluginDaemon[]
     */
    public function daemons(): array
    {
        return [];
    }

    public function wrapUp(): void
    {
        //
    }
}
