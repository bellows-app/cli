<?php

namespace Bellows;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\PluginDaemon;
use Bellows\Data\PluginJob;
use Bellows\Data\PluginWorker;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;
use ReflectionClass;

abstract class Plugin
{
    use InteractsWithConfig;
    use MakesEnabledDecisions;

    public int $priority = 0;

    // protected Env $localEnv;
    // The site we're currently deploying to
    protected SiteInterface $site;

    // If load balancing, the primary site, if not, the same as the $site property
    protected SiteInterface $primarySite;

    // The server we're currently deploying to
    protected ServerInterface $server;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerInterface $primaryServer;

    public function getName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    public function setSite(SiteInterface $site): self
    {
        $this->site = $site;

        // If we don't have a primary site at this point, also set this as the primary site
        // TODO: Is this an ugly/unexpected side effect? Oof.
        $this->primarySite ??= $site;

        return $this;
    }

    public function setPrimarySite(SiteInterface $site): self
    {
        $this->primarySite = $site;

        return $this;
    }

    public function setServer(ServerInterface $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function setPrimaryServer(ServerInterface $primaryServer): self
    {
        $this->primaryServer = $primaryServer;

        return $this;
    }

    public function setup(): void
    {
        //
    }

    public function createSiteParams(CreateSiteParams $params): array
    {
        return [];
    }

    public function installRepoParams(InstallRepoParams $baseParams): array
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

    public function canDeploy(): bool
    {
        return false;
    }

    public function canLaunch(): bool
    {
        return true;
    }
}
