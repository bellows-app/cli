<?php

namespace Bellows\Commands;

use Bellows\Data\Daemon;
use Bellows\Data\ForgeServer;
use Bellows\Data\Job;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Worker;
use Bellows\Dns\DnsFactory;
use Bellows\Dns\DnsProvider;
use Bellows\Env;
use Bellows\PluginManager;
use Bellows\ServerProviders\Forge\Forge;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class Launch extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'launch';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Launch the current repository as a site on a Forge server.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(PluginManager $pluginManager, Forge $forge)
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

        if ($server === null) {
            $this->error('No servers found!');
            $this->error('Provision a server on Forge first then come on back: https://forge.laravel.com/servers');
            $this->newLine();

            return;
        }

        App::instance(ForgeServer::class, $server);

        $localEnv = new Env(file_get_contents($dir . '/.env'));

        $host = parse_url($localEnv->get('APP_URL'), PHP_URL_HOST);

        $appName = $this->ask('App Name', $localEnv->get('APP_NAME'));
        $domain = $this->ask('Domain', Str::replace('.test', '.com', $host));

        $this->newLine();

        if ($existingSite = $server->getSiteByDomain($domain)) {
            if ($this->confirm('View existing site in Forge?', true)) {
                Process::run("open https://forge.laravel.com/servers/{$server->id}/sites/{$existingSite->id}");
            }

            return;
        }

        $isolatedUser = $this->ask(
            'Isolated User',
            Str::snake($appName),
        );

        [$repo, $repoBranch] = $this->getGitInfo($dir, $domain);

        $dnsProvider = DnsFactory::fromDomain($domain);

        if (!$dnsProvider) {
            $this->miniTask('Unsupported DNS provider', $domain, false);
        } else {
            $this->miniTask('Detected DNS provider', $dnsProvider->getName());

            if (!$dnsProvider->setCredentials()) {
                // We found a DNS provider, but they don't want to use it
                $dnsProvider = null;
            }
        }

        if ($dnsProvider) {
            $secureSite = $this->confirm('Secure site (enable SSL)?', true);
        }

        $this->newLine();

        App::instance(DnsProvider::class, $dnsProvider);

        [$phpVersion, $phpVersionBinary] = $server->phpVersionFromProject($dir);

        $projectConfig = new ProjectConfig(
            isolatedUser: $isolatedUser,
            repositoryUrl: $repo,
            repositoryBranch: $repoBranch,
            phpVersion: $phpVersion,
            phpBinary: $phpVersionBinary,
            projectDirectory: $dir,
            domain: $domain,
            appName: $appName,
            secureSite: $secureSite ?? false,
        );

        App::instance(ProjectConfig::class, $projectConfig);

        $this->step('Plugins');

        $pluginManager->setActive();

        $this->info('💨 Off we go!');

        $this->step('Site');

        $baseParams = [
            'domain'       => $domain,
            'project_type' => 'php',
            'directory'    => '/public',
            'isolated'     => true,
            'username'     => $isolatedUser,
            'php_version'  => $phpVersion,
        ];

        $createSiteParams = array_merge(
            $baseParams,
            ...$pluginManager->createSiteParams($baseParams),
        );

        /** @var \Bellows\ServerProviders\Forge\Site */
        $site = $this->withSpinner(
            title: 'Creating',
            task: fn () => $server->createSite($createSiteParams),
        );

        $baseRepoParams = [
            'provider'   => 'github',
            'repository' => $repo,
            'branch'     => $repoBranch,
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
                'APP_NAME'     => $appName,
                'APP_URL'      => "http://{$domain}",
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
            array_keys($localEnv->all())
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
                    fn ($k) => $siteEnv->update($k, $localEnv->get($k))
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
            fn (Daemon $daemon) => Daemon::from([
                'command'   => $daemon->command,
                'user'      => $daemon->user ?: $projectConfig->isolatedUser,
                'directory' => $daemon->directory
                    ?: "/home/{$projectConfig->isolatedUser}/{$projectConfig->domain}",
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
            fn (Worker $worker) => Worker::from(
                array_merge(
                    ['php_version' => $projectConfig->phpVersion],
                    $worker->toArray()
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
            fn (Job $job) => Job::from(
                array_merge(
                    ['user' => $projectConfig->isolatedUser],
                    $job->toArray()
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

        $siteUrl = "https://forge.laravel.com/servers/{$server['id']}/sites/{$site['id']}/application";

        $this->info($siteUrl);

        $this->newLine();

        if ($this->confirm('Open site in Forge?', true)) {
            Process::run("open {$siteUrl}");
        }
    }

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

        $repoUrlResult = trim(
            Process::run('git config --get remote.origin.url')->output()
        );

        $branchesResult = Process::run('git branch -a')->output();

        $branches = collect(explode(PHP_EOL, $branchesResult))
            ->map(fn ($b) => trim($b))
            ->filter()
            ->values();

        $mainBranch = Str::of($branches->first(fn ($b) => Str::startsWith($b, '*')))->replace('*', '')->trim();

        $devBranch = $branches->first(fn ($b) => in_array($b, ['develop', 'dev', 'development']));

        $branchChoices = $branches->map(
            fn ($b) => Str::of($b)->replace(['*', 'remotes/origin/'], '')->trim()->toString()
        )->filter(fn ($b) => !Str::startsWith($b, 'HEAD ->'))->filter()->values();

        $defaultRepo = collect(explode(':', $repoUrlResult))->map(
            fn ($p) => Str::replace('.git', '', $p)
        )->last();

        $repo = $this->ask('Repository', $defaultRepo);

        if ($repo === $defaultRepo) {
            // Only offer up possible branches if this is the repo we thought it was
            $repoBranch = $this->anticipate(
                'Repository Branch',
                $branchChoices,
                Str::contains($domain, 'dev.') ? $devBranch : $mainBranch
            );
        } else {
            $repoBranch = $this->ask('Repository Branch');
        }

        return [$repo, $repoBranch];
    }
}
