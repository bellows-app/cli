<?php

namespace Bellows;

use Bellows\Data\ForgeServer;
use Bellows\Data\ProjectConfig;
use Bellows\Dns\DnsProvider;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;

abstract class Plugin
{
    use InteractsWithConfig;
    use MakesEnabledDecisions;

    public $priority = 0;

    protected Env $localEnv;

    public function __construct(
        protected ProjectConfig $projectConfig,
        protected Config $config,
        protected Http $http,
        protected Console $console,
        protected Composer $composer,
        protected Npm $npm,
        protected DeployScript $deployScript,
        protected Artisan $artisan,
        protected ForgeServer $forgeServer,
        protected ?DnsProvider $dnsProvider = null,
    ) {
        $this->localEnv = new Env(file_get_contents($projectConfig->projectDirectory . '/.env'));
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

    public function setEnvironmentVariables(): array
    {
        return [];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $deployScript;
    }

    /**
     * @return \Bellows\Data\Worker[]
     */
    public function workers(): array
    {
        return [];
    }

    /**
     * @return \Bellows\Data\Job[]
     */
    public function jobs(): array
    {
        return [];
    }

    /**
     * @return \Bellows\Data\Daemon[]
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
