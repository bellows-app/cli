<?php

namespace Bellows\Commands;

use Bellows\Contracts\ServerProviderServer;
use Bellows\Data\DeploymentData;
use Bellows\Data\LaunchData;
use Bellows\Dns\DnsFactory;
use Bellows\Exceptions\EnvMissing;
use Bellows\Git\Repo;
use Bellows\PluginManagers\LaunchManager;
use Bellows\PluginSdk\Data\Repository;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Processes\CreateDaemons;
use Bellows\Processes\CreateJobs;
use Bellows\Processes\CreateSite;
use Bellows\Processes\CreateWorkers;
use Bellows\Processes\EnableQuickDeploy;
use Bellows\Processes\InstallRepository;
use Bellows\Processes\SetLaunchEnvironmentVariables;
use Bellows\Processes\SetupSSL;
use Bellows\Processes\SummarizeDeployment;
use Bellows\Processes\UpdateDeploymentScript;
use Bellows\Processes\UpdateDomainDNS;
use Bellows\Processes\WrapUpDeployment;
use Bellows\ServerProviders\ServerProviderInterface;
use Bellows\Util\Domain;
use Exception;
use Illuminate\Support\Facades\Pipeline;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class Launch extends Command
{
    protected $signature = 'launch';

    protected $description = 'Launch the current repository as a site on a Forge server.';

    public function handle(LaunchManager $pluginManager, ServerProviderInterface $serverProvider)
    {
        // Why are we warning? After Laravel 10 warn needs to be called at
        // least once before being able to use the <warning></warning> tags. Not sure why.
        $this->warn('');
        $this->info("🚀 Launch time! Let's do this.");
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

        $serverDeployTarget = $serverProvider->getServerDeployTargetFromServer($server);

        $appName = $this->ask('App Name', Project::env()->get('APP_NAME'));

        $domain = $serverDeployTarget->getDomain();

        $serverDeployTarget->setupForLaunch();

        $servers = $serverDeployTarget->servers();

        if ($existingSite = $serverDeployTarget->getExistingSite()) {
            $this->newLine();
            if ($this->confirm('View existing site in Forge?', true)) {
                Process::run('open ' . $existingSite->url());
            }

            return;
        }

        $this->newLine();

        $isolatedUser = $this->ask('Isolated User', Str::snake($appName));

        Project::setRepo($this->getGitInfo($dir, $domain));

        $dnsProvider = DnsFactory::fromDomain($domain);

        $aRecord = dns_get_record(Domain::getBaseDomain($domain), DNS_A)[0]['ip'] ?? null;

        if ($dnsProvider || $aRecord === $server->data()->ip_address) {
            $this->newLine();
            $secureSite = $dnsProvider ? $this->confirm('Secure site (enable SSL)?', true) : false;
        }

        $this->newLine();

        $phpVersion = $serverDeployTarget->determinePhpVersion();

        Project::setAppName($appName);
        Project::setIsolatedUser($isolatedUser);
        Project::setPhpVersion($phpVersion);
        Project::setDomain($domain);
        Project::setSiteIsSecure($secureSite ?? false);

        $this->step('Plugins');

        Deployment::setPrimaryServer($server);
        Deployment::setPrimarySite($serverDeployTarget->getPrimarySite());

        $pluginManager->setActive();

        $this->info('💨 Off we go!');

        $siteUrls = $servers->map(
            fn (ServerProviderServer $server) => $this->createSite($server, $pluginManager)
        );

        if ($siteUrls->count() === 1) {
            $siteUrl = $siteUrls->first();

            $this->info($siteUrl);
            $this->newLine();

            if ($this->confirm('Open site in Forge?', true)) {
                Process::run("open {$siteUrl}");
            }
        } else {
            $this->info('Sites created:');
            // TODO: Also list primary load balancing site
            $siteUrls->each(fn (string $siteUrl) => $this->info($siteUrl));
            $this->newLine();
        }
    }

    protected function createSite(
        ServerProviderServer $server,
        LaunchManager $pluginManager,
    ): string {
        Deployment::setServer($server);

        $this->step("Launching on {$server->name}");

        $launchData = new LaunchData(
            manager: $pluginManager,
        );

        Pipeline::send($launchData)->through([
            CreateSite::class,
            InstallRepository::class,
        ])->thenReturn();

        $deployData = new DeploymentData(
            manager: $pluginManager,
        );

        Pipeline::send($deployData)->through([
            UpdateDomainDNS::class,
            SetLaunchEnvironmentVariables::class,
            UpdateDeploymentScript::class,
            CreateDaemons::class,
            CreateWorkers::class,
            CreateJobs::class,
            SetupSSL::class,
            EnableQuickDeploy::class,
            WrapUpDeployment::class,
            SummarizeDeployment::class,
        ])->thenReturn();

        $this->step('Environment Variables');

        $this->newLine();
        $this->info('🎉 Site created successfully!');
        $this->newLine();

        return Deployment::site()->url();
    }

    protected function getGitInfo(string $dir, string $domain): Repository
    {
        if (!is_dir($dir . '/.git')) {
            $this->warn('Git repository not detected! Are you sure you are in the right directory?');

            return new Repository(
                url: $this->ask('Repository'),
                branch: $this->ask('Repository Branch'),
            );
        }

        // TODO: Why the current directory? Shouldn't it be the project directory like everything else?
        // This is really just naming convention, but still.
        $info = Repo::getInfoFromCurrentDirectory();

        $repo = $this->ask('Repository', $info->name);

        if ($repo === $info->name) {
            // Only offer up possible branches if this is the repo we thought it was
            $repoBranch = $this->anticipate(
                'Repository Branch',
                $info->branches->toArray(),
                Str::contains($domain, 'dev.') ? $info->devBranch : $info->mainBranch
            );
        } else {
            $repoBranch = $this->ask('Repository Branch');
        }

        return new Repository(
            url: $repo,
            branch: $repoBranch,
        );
    }
}
