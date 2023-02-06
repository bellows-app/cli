<?php

namespace App\DeployMate;

use App\DeployMate\Data\ProjectConfig;
use App\DeployMate\Dns\DnsProvider;
use App\DeployMate\PackageManagers\Composer;
use App\DeployMate\PackageManagers\Npm;
use Illuminate\Console\Concerns\InteractsWithIO;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Plugin
{
    use InteractsWithIO;
    use InteractsWithConfig;
    use MakesEnabledDecisions;

    protected Http $http;

    protected Composer $composer;

    protected Npm $npm;

    protected DeployScript $deployScript;

    protected Artisan $artisan;

    protected Env $env;

    public function __construct(
        protected ProjectConfig $projectConfig,
        protected Config $config,
        protected ?DnsProvider $dnsProvider = null,
        InputInterface $input,
        OutputInterface $output,
    ) {
        // TODO: Maybe bind this to the container? Feels outdated and a bit gross.
        $this->output = $output;
        $this->input = $input;
        $this->composer = new Composer($projectConfig);
        $this->npm = new Npm($projectConfig);
        $this->deployScript = new DeployScript($projectConfig);
        $this->artisan = new Artisan($projectConfig);
        $this->env = new Env($projectConfig);
        $this->http = new Http($config, $input, $output);
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
     * @return \App\DeployMate\Data\Worker[]
     */
    public function workers($server, $site): array
    {
        return [];
    }

    /**
     * @return \App\DeployMate\Data\Job[]
     */
    public function jobs($server, $site): array
    {
        return [];
    }

    /**
     * @return \App\DeployMate\Data\Daemon[]
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
