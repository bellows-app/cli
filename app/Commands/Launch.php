<?php

namespace Bellows\Commands;

use Bellows\Data\Daemon;
use Bellows\Data\Job;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\Data\Worker;
use Bellows\Dns\DnsFactory;
use Bellows\Dns\DnsProvider;
use Bellows\Exceptions\EnvMissing;
use Bellows\Facades\Project;
use Bellows\Git\Repo;
use Bellows\PluginManagers\LaunchManager;
use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\PluginSdk\Data\CreateSiteParams;
use Bellows\PluginSdk\Data\DaemonParams;
use Bellows\PluginSdk\Data\InstallRepoParams;
use Bellows\PluginSdk\Data\JobParams;
use Bellows\PluginSdk\Data\WorkerParams;
use Bellows\ServerProviders\ServerProviderInterface;
use Exception;
use Illuminate\Support\Facades\App;
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
            if ($this->confirm('View existing site in Forge?', true)) {
                Process::run(
                    "open https://forge.laravel.com/servers/{$existingSite->getServer()->id}/sites/{$existingSite->id}"
                );
            }

            return;
        }

        $this->newLine();

        $isolatedUser = $this->ask('Isolated User', Str::snake($appName));

        $repo = $this->getGitInfo($dir, $domain);

        $dnsProvider = $this->getDnsProvider($domain);

        $secureSite = $dnsProvider ? $this->confirm('Secure site (enable SSL)?', true) : false;

        $this->newLine();

        App::instance(DnsProvider::class, $dnsProvider);

        $phpVersion = $serverDeployTarget->determinePhpVersion();

        $projectConfig = new ProjectConfig(
            isolatedUser: $isolatedUser,
            repository: $repo,
            phpVersion: $phpVersion,
            directory: $dir,
            domain: $domain,
            appName: $appName,
            secureSite: $secureSite ?? false,
        );

        Project::setConfig($projectConfig);

        $this->step('Plugins');

        $pluginManager->setPrimaryServer($server);
        $pluginManager->setPrimarySite($serverDeployTarget->getPrimarySite());

        $pluginManager->setActive();

        $this->info('💨 Off we go!');

        $siteUrls = $servers->map(
            fn (ServerInterface $server) => $this->createSite($server, $pluginManager)
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

    protected function createSite(
        ServerInterface $server,
        LaunchManager $pluginManager,
    ): string {
        $pluginManager->setServer($server);

        $this->step("Launching on {$server->name}");

        $this->step('Site');

        $baseParams = new CreateSiteParams(
            domain: Project::config()->domain,
            projectType: 'php',
            directory: '/public',
            isolated: true,
            username: Project::config()->isolatedUser,
            phpVersion: Project::config()->phpVersion->version,
        );

        $createSiteParams = $pluginManager->createSiteParams($baseParams->toArray());

        /** @var SiteInterface $site */
        $site = $this->withSpinner(
            title: 'Creating',
            task: fn () => $server->createSite(CreateSiteParams::from($createSiteParams)),
        );

        $pluginManager->setSite($site);

        $baseRepoParams = new InstallRepoParams(
            provider: 'github',
            repository: Project::config()->repository->url,
            branch: Project::config()->repository->branch,
            composer: true,
        );

        $installRepoParams = $pluginManager->installRepoParams($baseRepoParams->toArray());

        $this->step('Repository');

        $this->withSpinner(
            title: 'Installing',
            task: fn () => $site->installRepo(InstallRepoParams::from($installRepoParams)),
        );

        $this->step('Environment Variables');

        $siteEnv = $site->env();

        $updatedEnvValues = collect($pluginManager->environmentVariables([
            'APP_NAME'      => Project::config()->appName,
            'APP_URL'       => 'http://' . Project::config()->domain,
            'VITE_APP_ENV'  => '${APP_ENV}',
            'VITE_APP_NAME' => '${APP_NAME}',
        ]));

        $updatedEnvValues->each(
            fn ($v, $k) => $siteEnv->update(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        $inLocalButNotInRemote = collect(
            array_keys(Project::env()->all())
        )->diff(
            array_keys($siteEnv->all())
        )->values();

        if ($inLocalButNotInRemote->isNotEmpty()) {
            $this->newLine();
            $this->info('The following environment variables are in your local .env file but not in your remote .env file:');
            $this->newLine();

            $inLocalButNotInRemote->each(
                fn ($k) => $this->comment($k)
            );

            if ($this->confirm('Would you like to add any of them? They will be added with their existing values.')) {
                $toAdd = $this->choice(
                    question: 'Which environment variables would you like to add?',
                    choices: $inLocalButNotInRemote->toArray(),
                    multiple: true,
                );

                collect($toAdd)->each(
                    fn ($k) => $siteEnv->update($k, Project::env()->get($k))
                );
            }
        }

        $this->withSpinner(
            title: 'Updating',
            task: fn () => $site->updateEnv($siteEnv->toString()),
        );

        $this->step('Deploy Script');

        $deployScript = $site->getDeploymentScript();
        ray($deployScript);
        $updatedDeployScript = $pluginManager->updateDeployScript($deployScript);

        $this->withSpinner(
            title: 'Updating',
            task: fn () => $deployScript === $updatedDeployScript ? true : $site->updateDeploymentScript($updatedDeployScript),
        );

        $this->step('Daemons');

        $daemons = collect($pluginManager->daemons())->map(
            fn (DaemonParams $daemon) => Daemon::from([
                'command'   => $daemon->command,
                'user'      => $daemon->user ?: Project::config()->isolatedUser,
                'directory' => $daemon->directory
                    ?: '/' . collect([
                        'home',
                        Project::config()->isolatedUser,
                        Project::config()->domain,
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
                    ['php_version' => $worker->phpVersion ?? Project::config()->phpVersion->version],
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
                    ['user' => $job->user ?? Project::config()->isolatedUser],
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
                    $deployScript,
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
        $this->info('🎉 Site created successfully!');
        $this->newLine();

        $siteUrl = "https://forge.laravel.com/servers/{$server->id}/sites/{$site->id}/application";

        return $siteUrl;
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
