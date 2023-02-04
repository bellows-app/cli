<?php

namespace App\DeployMate;

use App\DeployMate\Data\ProjectConfig;
use App\DeployMate\PackageManagers\Composer;
use App\DeployMate\PackageManagers\Npm;
use Illuminate\Console\Concerns\InteractsWithIO;

abstract class Plugin
{
    use InteractsWithIO;
    use InteractsWithConfig;
    use MakesEnabledDecisions;

    protected Composer $composer;

    protected Npm $npm;

    protected DeployScript $deployScript;

    protected Artisan $artisan;

    protected Env $env;

    public function __construct(protected ProjectConfig $projectConfig, protected Config $config, $output, $input)
    {
        // TODO: Maybe bind this to the container? Feels outdated and a bit gross.
        $this->output = $output;
        $this->input = $input;
        $this->composer = new Composer($projectConfig);
        $this->npm = new Npm($projectConfig);
        $this->deployScript = new DeployScript($projectConfig);
        $this->artisan = new Artisan($projectConfig);
        $this->env = new Env($projectConfig);
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
