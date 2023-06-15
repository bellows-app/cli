<?php

namespace Bellows\Commands;

use Bellows\Data\Daemon;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\Data\Worker;
use Bellows\Dns\DnsFactory;
use Bellows\Dns\DnsProvider;
use Bellows\Exceptions\EnvMissing;
use Bellows\Git\Repo;
use Bellows\PluginManagers\DeploymentManager;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\PluginSdk\Data\DaemonParams;
use Bellows\PluginSdk\Data\JobParams;
use Bellows\PluginSdk\Data\SecurityRule;
use Bellows\PluginSdk\Data\WorkerParams;
use Bellows\PluginSdk\Facades\Project;
use Bellows\ServerProviders\Forge\Site;
use Bellows\ServerProviders\ServerProviderInterface;
use Exception;
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

        $siteChoices = $server->getSites()->map(
            fn (ForgeSite $site) => $site->name,
        )->sort()->values()->toArray();

        $siteName = $this->choice(
            'Which site would you like to deploy to?',
            $siteChoices,
        );

        /** @var ForgeSite $site */
        $site = $server->getSites()->first(fn (ForgeSite $site) => $site->name === $siteName);

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

        $siteUrls = $sites->map(function (SiteInterface $site) use ($server, $siteProvider, $phpVersion, $dir) {
            $siteEnv = $site->env();

            $projectConfig = new ProjectConfig(
                isolatedUser: $site->username,
                repository: new Repository($site->repository, $site->repository_branch),
                phpVersion: $phpVersion,
                directory: $dir,
                domain: $site->name,
                appName: $siteEnv->get('APP_NAME') ?? '',
                secureSite: Str::contains($siteEnv->get('APP_URL'), 'https://'),
            );

            Project::setConfig($projectConfig);

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

    protected function deployToSite(
        SiteInterface $site,
        DeploymentManager $pluginManager,
    ): string {
        $server = $site->getServerProvider();

        $pluginManager->setSite($site);
        $pluginManager->setServer($server);

        $this->step("Deploying to {$site->name} ({$site->getServer()->name})");

        $this->step('Environment Variables');

        $siteEnv = $site->getEnv();

        $updatedEnvValues = collect($pluginManager->environmentVariables());

        $updatedEnvValues->each(
            fn ($v, $k) => $siteEnv->update(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        $this->withSpinner(
            title: 'Updating',
            task: fn () => $site->updateEnv($siteEnv->toString()),
        );

        $this->step('Deploy Script');

        $deployScript = $site->getDeploymentScript();
        $updatedDeployScript = $pluginManager->updateDeployScript($deployScript);

        $this->withSpinner(
            title: 'Updating',
            task: fn () => $deployScript === $updatedDeployScript ? true : $site->updateDeploymentScript($updatedDeployScript),
        );

        $this->step('Daemons');

        $daemons = collect($pluginManager->daemons())->map(
            fn (DaemonParams $daemon) => Daemon::from([
                'command'   => $daemon->command,
                'user'      => $daemon->user ?: Project::isolatedUser(),
                'directory' => $daemon->directory
                    ?: '/' . collect([
                        'home',
                        Project::isolatedUser(),
                        Project::domain(),
                    ])->join('/'),
            ])
        );

        $this->withSpinner(
            title: 'Creating',
            task: fn () => $daemons->each(
                fn ($daemon) => $server->createDaemon($daemon),
            ),
        );

        $this->step('Workers');

        $workers = collect($pluginManager->workers())->map(
            fn (WorkerParams $worker) => Worker::from(
                array_merge(
                    $worker->toArray(),
                    ['php_version' => $worker->phpVersion ?? Project::phpVersion()->version],
                )
            )
        );

        $this->withSpinner(
            title: 'Creating',
            task: fn () => $workers->each(
                fn ($worker) => $site->createWorker($worker)
            ),
        );

        $this->step('Scheduled Jobs');

        $jobs = collect($pluginManager->jobs())->map(
            fn (JobParams $job) => Job::from(
                array_merge(
                    $job->toArray(),
                    ['user' => $job->user ?? Project::isolatedUser()],
                ),
            )
        );

        $this->withSpinner(
            title: 'Creating',
            task: fn () => $jobs->each(
                fn ($job) => $server->createJob($job)
            ),
        );

        $this->step('Wrapping Up');

        collect($pluginManager->securityRules())->each(
            fn (SecurityRule $rule) => $site->addSecurityRule($rule)
        );

        $this->withSpinner(
            title: 'Cooling the rockets',
            task: fn () => $pluginManager->wrapUp(),
        );

        $this->step('Summary');

        $this->table(
            ['Task', 'Value'],
            collect([
                [
                    'Environment Variables',
                    $updatedEnvValues->isNotEmpty() ? $updatedEnvValues->keys()->join(PHP_EOL) : '-',
                ],
                [
                    'Deploy Script',
                    $updatedDeployScript !== $deployScript ? $deployScript : '-',
                ],
                [
                    'Daemons',
                    $daemons->isNotEmpty() ? $daemons->pluck('command')->join(PHP_EOL) : '-',
                ],
                [
                    'Workers',
                    $workers->isNotEmpty() ? $workers->pluck('connection')->join(PHP_EOL) : '-',
                ],
                [
                    'Scheduled Jobs',
                    $jobs->isNotEmpty() ? $jobs->pluck('command')->join(PHP_EOL) : '-',
                ],
            ])->map(fn ($row) => [
                "<comment>{$row[0]}</comment>",
                $row[1] . PHP_EOL,
            ])->toArray(),
        );

        $this->newLine();
        $this->info('🎉 Site deployed successfully!');
        $this->newLine();

        return "https://forge.laravel.com/servers/{$server->id}/sites/{$site->id}/application";
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
