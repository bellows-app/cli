<?php

namespace Bellows\Commands;

use Bellows\Data\Daemon;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Bellows\Data\PluginDaemon;
use Bellows\Data\PluginJob;
use Bellows\Data\PluginWorker;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Worker;
use Bellows\Dns\DnsFactory;
use Bellows\Dns\DnsProvider;
use Bellows\Env;
use Bellows\Git\Repo;
use Bellows\PluginManagerInterface;
use Bellows\Project;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\ServerProviderInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class Launch extends Command
{
    protected $signature = 'launch';

    protected $description = 'Launch the current repository as a site on a Forge server.';

    public function handle(PluginManagerInterface $pluginManager, ServerProviderInterface $forge)
    {
        // Why are we warning? After Laravel 10 warn needs to be called at
        // least once before being able to use the <warning></warning> tags. Not sure why.
        $this->warn('');
        $this->info("🚀 Launch time! Let's do this.");
        $this->newLine();

        $forge->setCredentials();

        $dir = rtrim(getcwd(), '/');

        if (!file_exists($dir . '/.env')) {
            $this->error('No .env file found! Are you in the correct directory?');
            $this->info('Bellows works best when it has access to an .env file.');
            $this->newLine();

            return;
        }

        $server = $forge->getServer();

        // Do we we spin up a factory object here to start handling this server vs servers situation?

        if ($server === null) {
            $this->error('No servers found!');
            $this->error('Provision a server on Forge first then come on back: https://forge.laravel.com/servers');
            $this->newLine();

            return;
        }

        if ($server->type === 'loadbalancer') {
            $this->miniTask('Detected', 'Load Balancer', true);

            $loadBalancedSite = $forge->getLoadBalancedSite($server->id);

            /** @var Collection<ServerInterface> $servers */
            $servers = $forge->getLoadBalancedServers($server->id, $loadBalancedSite->id);
        } else {
            /** @var Collection<ServerInterface> $servers */
            $servers = collect([$server]);
        }

        $localEnv = new Env(file_get_contents($dir . '/.env'));

        $host = parse_url($localEnv->get('APP_URL'), PHP_URL_HOST);

        $appName = $this->ask('App Name', $localEnv->get('APP_NAME'));

        $domain = $this->getDomain($loadBalancedSite ?? null, $host);

        if ($this->siteAlreadyExists($servers, $domain)) {
            return;
        }

        $this->newLine();

        $isolatedUser = $this->ask('Isolated User', Str::snake($appName));

        [$repo, $repoBranch] = $this->getGitInfo($dir, $domain);

        $dnsProvider = $this->getDnsProvider($domain);

        $secureSite = $dnsProvider ? $this->confirm('Secure site (enable SSL)?', true) : false;

        $this->newLine();

        App::instance(DnsProvider::class, $dnsProvider);

        $phpVersion = $this->determinePhpVersion($servers, $dir);

        $projectConfig = new ProjectConfig(
            isolatedUser: $isolatedUser,
            repositoryUrl: $repo,
            repositoryBranch: $repoBranch,
            phpVersion: $phpVersion,
            directory: $dir,
            domain: $domain,
            appName: $appName,
            secureSite: $secureSite ?? false,
        );

        $project = new Project($projectConfig);

        App::instance(Project::class, $project);

        $this->step('Plugins');

        $pluginManager->setLoadBalancingServer($server);

        if (isset($loadBalancedSite)) {
            $pluginManager->setLoadBalancingSite($loadBalancedSite);
        }

        $pluginManager->setActive();

        $this->info('💨 Off we go!');

        $siteUrls = $servers->map(
            fn (ServerInterface $server) => $this->createSite($server, $pluginManager, $project)
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
            $siteUrls->each(fn (string $siteUrl) => $this->info($siteUrl));
            $this->newLine();
        }
    }

    protected function determinePhpVersion(Collection $servers, string $dir): PhpVersion
    {
        if ($servers->count() === 1) {
            // If it's just the one server, we can just ask the server what's up
            return $this->withSpinner(
                title: 'Determining PHP version',
                task: fn () => $servers->first()->determinePhpVersionFromProject($dir),
                message: fn (?PhpVersion $result) => $result?->display,
                success: fn ($result) => $result !== null,
            );
        }

        // Figure out the PHP version for the load balanced sites
        $versions = $this->withSpinner(
            title: 'Determining installed PHP versions',
            task: fn () => $servers->map(fn (ServerInterface $server) => $server->validPhpVersionsFromProject($dir)),
            message: fn ($result) => $result->flatten()->unique('version')->sortByDesc('version')->values()->map(fn (PhpVersion $version) => $version->display)->join(', '),
            success: fn ($result) => true,
        );

        $flattened = $versions->flatten();

        $byVersion = $flattened->groupBy('version');

        $commonVersion = $byVersion->first(fn ($versions) => $versions->count() === $servers->count());

        if ($commonVersion) {
            $this->miniTask('Using PHP version', $commonVersion->first()->display, true);

            return $commonVersion->first();
        }

        $phpVersions = $flattened->unique('version')->sortByDesc('version')->values();

        $selectedVersion = $this->choice(
            'Select PHP version',
            $phpVersions->map(
                fn (PhpVersion $version) => Str::replace('PHP ', '', $version->display)
            )->toArray(),
        );

        $phpVersion = $phpVersions->first(
            fn (PhpVersion $version) => Str::replace('PHP ', '', $version->display) === $selectedVersion
        );

        $servers->each(fn (ServerInterface $server) => $server->installPhpVersion($phpVersion->version));

        return $phpVersion;
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

    protected function getDomain(?SiteInterface $loadBalancedSite, string $host): string
    {
        if ($loadBalancedSite) {
            $this->miniTask('Domain', $loadBalancedSite->name, true);

            return $loadBalancedSite->name;
        }

        return $this->ask('Domain', Str::replace('.test', '.com', $host));
    }

    protected function siteAlreadyExists(Collection $servers, string $domain): bool
    {
        return $servers->first(function (ServerInterface $server) use ($domain) {
            $existing = $server->getSiteByDomain($domain);

            if (!$existing) {
                return false;
            }

            if ($this->confirm('View existing site in Forge?', true)) {
                Process::run("open https://forge.laravel.com/servers/{$server->id}/sites/{$existing->id}");
            }

            return true;
        }) !== null;
    }

    protected function createSite(
        ServerInterface $server,
        PluginManagerInterface $pluginManager,
        Project $project,
    ): string {
        $pluginManager->setServer($server);

        $this->step("Launching on {$server->name}");

        $this->step('Site');

        // TODO: DTO?
        $baseParams = [
            'domain'       => $project->config->domain,
            'project_type' => 'php',
            'directory'    => '/public',
            'isolated'     => true,
            'username'     => $project->config->isolatedUser,
            'php_version'  => $project->config->phpVersion->version,
        ];

        $createSiteParams = array_merge(
            $baseParams,
            ...$pluginManager->createSiteParams($baseParams),
        );

        /** @var SiteInterface $site */
        $site = $this->withSpinner(
            title: 'Creating',
            task: fn () => $server->createSite($createSiteParams),
        );

        $pluginManager->setSite($site);

        $baseRepoParams = [
            'provider'   => 'github',
            'repository' => $project->config->repositoryUrl,
            'branch'     => $project->config->repositoryBranch,
            'composer'   => true,
        ];

        $installRepoParams = array_merge(
            $baseRepoParams,
            ...$pluginManager->installRepoParams($baseRepoParams),
        );

        $this->step('Repository');

        $this->withSpinner(
            title: 'Installing',
            task: fn () => $site->installRepo($installRepoParams),
        );

        $this->step('Environment Variables');

        $siteEnv = new Env($site->getEnv());

        $updatedEnvValues = collect(array_merge(
            [
                'APP_NAME'     => $project->config->appName,
                'APP_URL'      => "http://{$project->config->domain}",
                'VITE_APP_ENV' => '${APP_ENV}',
            ],
            $pluginManager->environmentVariables(),
        ));

        $updatedEnvValues->map(
            fn ($v, $k) => $siteEnv->update(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        $inLocalButNotInRemote = collect(
            array_keys($project->env->all())
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
                    fn ($k) => $siteEnv->update($k, $project->env->get($k))
                );
            }
        }

        $this->withSpinner(
            title: 'Updating',
            task: fn () => $site->updateEnv($siteEnv->toString()),
        );

        $this->step('Deploy Script');

        $deployScript = $site->getDeploymentScript();
        $deployScript = $pluginManager->updateDeployScript($deployScript);

        $this->withSpinner(
            title: 'Updating',
            task: fn () => $site->updateDeploymentScript($deployScript),
        );

        $this->step('Daemons');

        $daemons = $pluginManager->daemons()->map(
            fn (PluginDaemon $daemon) => Daemon::from([
                'command'   => $daemon->command,
                'user'      => $daemon->user ?: $project->config->isolatedUser,
                'directory' => $daemon->directory
                    ?: "/home/{$project->config->isolatedUser}/{$project->config->domain}",
            ])
        );

        $this->withSpinner(
            title: 'Creating',
            task: fn () => $daemons->each(
                fn ($daemon) => $server->createDaemon($daemon),
            ),
        );

        $this->step('Workers');

        $workers = $pluginManager->workers()->map(
            fn (PluginWorker $worker) => Worker::from(
                array_merge(
                    $worker->toArray(),
                    ['php_version' => $worker->phpVersion ?? $project->config->phpVersion->version],
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

        $jobs = $pluginManager->jobs()->map(
            fn (PluginJob $job) => Job::from(
                array_merge(
                    $job->toArray(),
                    ['user' => $job->user ?? $project->config->isolatedUser],
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

    // TODO: Move this to Console mixin
    protected function step($title)
    {
        $padding = 2;
        $length = max(40, strlen($title) + ($padding * 2));

        $this->newLine();
        $this->info('+' . str_repeat('-', $length) . '+');
        $this->info('|' . str_repeat(' ', $length) . '|');
        $this->info(
            '|'
                . str_repeat(' ', $padding)
                . '<comment>' . $title . '</comment>'
                . str_repeat(' ', $length - strlen($title) - $padding)
                . '|'
        );
        $this->info('|' . str_repeat(' ', $length) . '|');
        $this->info('+' . str_repeat('-', $length) . '+');
        $this->newLine();
    }

    protected function getGitInfo(string $dir, string $domain): array
    {
        if (!is_dir($dir . '/.git')) {
            $this->warn('Git repository not detected! Are you sure you are in the right directory?');

            return [
                $this->ask('Repository'),
                $this->ask('Repository Branch'),
            ];
        }

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

        return [$repo, $repoBranch];
    }
}
