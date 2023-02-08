<?php

namespace App\Bellows;

use App\Bellows\Data\ProjectConfig;
use App\Bellows\Dns\DnsProvider;
use App\Bellows\PackageManagers\Composer;
use App\Bellows\PackageManagers\Npm;

abstract class Plugin
{
    use InteractsWithConfig;
    use MakesEnabledDecisions;

    public $priority = 0;

    public function __construct(
        protected ProjectConfig $projectConfig,
        protected Config $config,
        protected Http $http,
        protected Console $console,
        protected Composer $composer,
        protected Npm $npm,
        protected DeployScript $deployScript,
        protected Artisan $artisan,
        protected Env $env,
        protected ?DnsProvider $dnsProvider = null,
    ) {
    }

    public function setup($server): void
    {
        //
    }

    public function createSiteParams(array $params): array
    {
        return [];
    }

    public function installRepoParams($server, $site, array $baseParams): array
    {
        return [];
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [];
    }

    public function updateDeployScript($server, $site, string $deployScript): string
    {
        return $deployScript;
    }

    /**
     * @return \App\Bellows\Data\Worker[]
     */
    public function workers($server, $site): array
    {
        return [];
    }

    /**
     * @return \App\Bellows\Data\Job[]
     */
    public function jobs($server, $site): array
    {
        return [];
    }

    /**
     * @return \App\Bellows\Data\Daemon[]
     */
    public function daemons($server, $site): array
    {
        return [];
    }

    public function wrapUp($server, $site): void
    {
        //
    }

    protected function getDefaultNewAccountName(string $token): ?string
    {
        return null;
    }
}
