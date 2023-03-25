<?php

namespace Bellows;

use Bellows\Data\ProjectConfig;
use Bellows\Dns\DnsProvider;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\ServerProviders\Forge\Server;
use Bellows\ServerProviders\Forge\Site;

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
        protected Server $server,
        protected Site $site,
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

    public function environmentVariables(): array
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
