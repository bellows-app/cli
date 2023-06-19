<?php

namespace Bellows\Commands;

use Bellows\Contracts\ServerProviderSite;
use Bellows\Data\DeploymentData;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\Dns\DnsFactory;
use Bellows\Dns\DnsProvider;
use Bellows\Exceptions\EnvMissing;
use Bellows\PluginManagers\DeploymentManager;
use Bellows\PluginSdk\Data\Site as SiteData;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Processes\CreateDaemons;
use Bellows\Processes\CreateJobs;
use Bellows\Processes\CreateWorkers;
use Bellows\Processes\SetEnvironmentVariables;
use Bellows\Processes\UpdateDeploymentScript;
use Bellows\Processes\WrapUpDeployment;
use Bellows\ServerProviders\Forge\Site;
use Bellows\ServerProviders\ServerProviderInterface;
use Exception;
use Illuminate\Support\Facades\Pipeline;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class Deploy extends Command
{
    protected $signature = 'deploy';

    protected $description = 'Deploy a feature(s) of the current repository to a site on a Forge server.';

    public function handle(ServerProviderInterface $serverProvider)
    {
        // Why are we warning? After Laravel 10 warn needs to be called at
        // least once before being able to use the <warning></warning> tags. Not sure why.
        $this->warn('');
        $this->info("🚀 Time to deploy! Let's do this.");
        $this->newLine();

        // Set the base credentials for the server provider (currently only Forge)
        $serverProvider->setCredentials();

        $dir = rtrim(getcwd(), '/');

        Project::setDir($dir);

        try {
            Project::env();
        } catch (EnvMissing $e) {
            $this->error('No .env file found! Are you in the correct directory?');
            $this->info('Bellows works best when it has access to an .env file.');
            $this->newLine();

            return;
        } catch (Exception $e) {
            $this->error('Something went wrong while trying to read the .env file.');
            $this->info('Bellows works best when it has access to an .env file.');
            $this->newLine();

            return;
        }

        $server = $serverProvider->getServer();

        if ($server === null) {
            $this->error('No servers found!');
            $this->error('Provision a server on Forge first then come on back: https://forge.laravel.com/servers');
            $this->newLine();

            return;
        }

        $siteChoices = $server->sites()->map(
            fn (SiteData $site) => $site->name,
        )->sort()->values()->toArray();

        $siteName = $this->choice(
            'Which site would you like to deploy to?',
            $siteChoices,
        );

        /** @var SiteData $site */
        $site = $server->sites()->first(fn (SiteData $site) => $site->name === $siteName);

        $siteProvider = new Site($site, $server->serverData());

        $serverDeployTarget = $serverProvider->getServerDeployTargetFromServer($server);

        $serverDeployTarget->setupForDeploy($siteProvider);

        $sites = $serverDeployTarget->sites();

        // TODO: Maybe we don't do this until we need to? Or rather, see if we need to?
        // $dnsProvider = $this->getDnsProvider($site->name);
        // App::instance(DnsProvider::class, $dnsProvider);

        $phpVersion = $siteProvider->getPhpVersion();

        $this->step('Plugins');

        $this->comment('Gathering some information...');
        $this->newLine();

        $siteUrls = $sites->map(function (ServerProviderSite $site) use ($server, $siteProvider, $phpVersion, $dir) {
            $siteEnv = $site->env();

            Project::setIsolatedUser($site->username);
            Project::setDomain($site->name);
            Project::setAppName($siteEnv->get('APP_NAME') ?? '');
            Project::setPhpVersion($phpVersion);
            // $projectConfig = new ProjectConfig(
            //     repository: new Repository($site->repository, $site->repository_branch),
            //     phpVersion: $phpVersion,
            //     directory: $dir,
            //     secureSite: Str::contains($siteEnv->get('APP_URL'), 'https://'),
            // );

            /** @var DeploymentManager $pluginManager */
            $pluginManager = app(DeploymentManager::class);
            $pluginManager->setPrimaryServer($server);
            $pluginManager->setPrimarySite($siteProvider);
            $pluginManager->setActive($site);

            $this->info('💨 Off we go!');
            $url = $this->deployToSite($site, $pluginManager);

            return $url;
        });

        $siteUrls->each(fn (string $siteUrl) => $this->info($siteUrl));
        $this->newLine();

        $descriptor = Str::plural('site', $siteUrls);

        if ($this->confirm("Open {$descriptor} in Forge?", true)) {
            Process::run($siteUrls->map(fn ($u) => 'open ' . $u)->join(' && '));
        }
    }

    protected function deployToSite(
        ServerProviderSite $site,
        DeploymentManager $pluginManager,
    ): string {
        $server = $site->getServerProvider();

        $pluginManager->setSite($site);
        $pluginManager->setServer($server);

        $this->step("Deploying to {$site->name} ({$site->getServer()->name})");

        $data = new DeploymentData(
            manager: $pluginManager,
            site: $site,
            server: $server,
        );

        $result = Pipeline::send($data)->through([
            SetEnvironmentVariables::class,
            UpdateDeploymentScript::class,
            CreateDaemons::class,
            CreateWorkers::class,
            CreateJobs::class,
            WrapUpDeployment::class,
        ])->thenReturn();

        if (count($result->summary) > 0) {
            $this->step('Summary');

            $this->table(
                ['Task', 'Value'],
                collect($result->summary)->map(fn ($row) => [
                    "<comment>{$row[0]}</comment>",
                    $row[1] . PHP_EOL,
                ])->toArray(),
            );
        }

        $this->newLine();
        $this->info('🎉 Site deployed successfully!');
        $this->newLine();

        return "https://forge.laravel.com/servers/{$server->id}/sites/{$site->id}/application";
    }

    protected function getDnsProvider(string $domain): ?DnsProvider
    {
        $dnsProvider = DnsFactory::fromDomain($domain);

        if (!$dnsProvider) {
            $this->miniTask('Unsupported DNS provider', $domain, false);

            return null;
        }

        $this->miniTask('Detected DNS provider', $dnsProvider->getName());

        if (!$dnsProvider->setCredentials()) {
            // We found a DNS provider, but they don't want to use it
            return null;
        }

        return $dnsProvider;
    }
}
