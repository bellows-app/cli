<?php

namespace Bellows;

use Bellows\Data\Job;
use Bellows\Data\PluginDaemon;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Worker;
use Bellows\Dns\DnsProvider;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;

abstract class Plugin
{
    use InteractsWithConfig;
    use MakesEnabledDecisions;

    public int $priority = 0;

    protected Env $localEnv;

    protected SiteInterface $site;

    public function __construct(
        protected ProjectConfig $projectConfig,
        protected Config $config,
        protected Http $http,
        protected Console $console,
        protected Composer $composer,
        protected Npm $npm,
        protected DeployScript $deployScript,
        protected Artisan $artisan,
        protected ServerInterface $server,
        protected ?DnsProvider $dnsProvider = null,
    ) {
        $this->localEnv = new Env(file_get_contents($projectConfig->projectDirectory . '/.env'));
    }

    public function setSite(SiteInterface $site): self
    {
        $this->site = $site;

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
     * @return Worker[]
     */
    public function workers(): array
    {
        return [];
    }

    /**
     * @return Job[]
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
