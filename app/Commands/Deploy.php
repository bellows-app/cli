<?php

namespace App\Commands;

use App\DeployMate\Config;
use App\DeployMate\Console;
use App\DeployMate\Data\Daemon;
use App\DeployMate\Data\Job;
use App\DeployMate\Plugin;
use App\DeployMate\Data\ProjectConfig;
use App\DeployMate\Data\Worker;
use App\DeployMate\Dns\DnsFactory;
use Composer\Semver\Semver;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;

class Deploy extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'deploy';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Deploy the current repo to a Forge server';

    protected string $siteEnv;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Config $config, Console $console)
    {
        $console->setInput($this->input);
        $console->setOutput($this->output);

        if (!$config->get('forge.token')) {
            $this->info('No Forge token found! You can get one here:');
            $this->info('https://forge.laravel.com/user-profile/api');

            $token = $this->secret('Forge API Token');

            $config->set('forge.token', $token);
        }

        // $this->task(
        //     'What is this exactly',
        //     function () {

        //         $this->newLine();

        //         $this->line('hm what now');
        //         $this->info('difference?');

        //         return true;
        //     }
        // );

        $dir = rtrim(getcwd(), '/');

        $forgeApiUrl = 'https://forge.laravel.com/api/v1';

        $repoUrl = exec('git config --get remote.origin.url');
        $defaultRepo = collect(explode(':', $repoUrl))->map(
            fn ($p) => Str::replace('.git', '', $p)
        )->last();

        Http::macro(
            'forge',
            fn () => Http::baseUrl($forgeApiUrl)
                ->withToken($config->get('forge.token'))
                ->acceptJson()
                ->asJson()
        );

        $servers = collect(
            Http::forge()->get('servers')->json()['servers']
        )->filter(fn ($s) => !$s['revoked'])->values();

        $serverName = $this->choice(
            'Which server?',
            $servers->pluck('name')
                // JUST FOR DEMO
                // ->filter(fn ($s) => in_array($s, ['projects', 'joe-codes', 'batzen', 'blip-tester']))
                ->values()
                ->toArray()
        );

        $server = $servers->first(fn ($s) => $s['name'] === $serverName);

        Http::macro(
            'forgeServer',
            fn () =>  Http::forge()->baseUrl("{$forgeApiUrl}/servers/{$server['id']}")
        );

        $localEnv = Dotenv::parse(file_get_contents($dir . '/.env'));

        $host = parse_url($localEnv['APP_URL'], PHP_URL_HOST);

        $appName      = $this->ask('App Name', $localEnv['APP_NAME']);
        $domain       = $this->ask('Domain', Str::replace('.test', '.com', $host));
        $isolatedUser = $this->ask('Isolated User', Str::of($host)->replace('.test', '')->replace('.', '_')->toString());
        $repo         = $this->ask('Repo', $defaultRepo);
        $repoBranch   = $this->ask('Repo Branch', Str::contains($domain, 'dev.') ? 'develop' : 'main');

        $dnsProvider = DnsFactory::fromDomain($domain);

        if (!$dnsProvider) {
            $this->warn('Unsupported DNS provider for ' . $domain);
        } else {
            $this->info('Using DNS provider: ' . $dnsProvider->getName());
            $dnsProvider->setCredentials();
        }

        $this->info('Configuring PHP version...');

        [$phpVersion, $phpVersionBinary] = $this->determinePhpVersion($dir);

        $this->line('PHP Version: ' . $phpVersionBinary);

        $projectConfig = new ProjectConfig(
            isolatedUser: $isolatedUser,
            repositoryUrl: $repo,
            repositoryBranch: $repoBranch,
            phpVersion: $phpVersion,
            phpBinary: $phpVersionBinary,
            projectDirectory: $dir,
            domain: $domain,
            appName: $appName,
        );

        $this->info('Loading plugins...');

        /** @var \Illuminate\Support\Collection<\App\ForgePlugins\BasePlugin> */
        $plugins = collect(config('forge.plugins'))
            // TODO: There is a better way to do this, figure it out. This is a bandaid.
            ->map(fn (string $plugin) => app($plugin, [
                'output'        => $this->output,
                'input'         => $this->input,
                'projectConfig' => $projectConfig,
                'config'        => $config,
                'dnsProvider'   => $dnsProvider,
            ]));

        $autoDecision = $plugins->filter(fn ($plugin) => $plugin->hasDefaultEnabled());

        $this->table(
            ['Plugin', 'Enabled', 'Reason'],
            $autoDecision->map(fn (Plugin $p) => [
                get_class($p),
                $p->getDefaultEnabled()->enabled ? '✓' : '',
                $p->getDefaultEnabled()->reason,
            ])->toArray(),
        );

        $defaultsAreGood = $autoDecision->count() > 0 ? $this->confirm('Continue?', true) : false;

        $activePlugins = $plugins->filter(function (Plugin $p) use ($server, $defaultsAreGood) {
            $enabled = $defaultsAreGood && $p->hasDefaultEnabled()
                ? $p->getDefaultEnabled()->enabled
                : $p->enabled();

            if (!$enabled) {
                return false;
            }

            $this->info("Configuring {$p->getName()} plugin...");

            $p->setup($server);

            return true;
        })->values();

        $baseParams = [
            'domain'       => $domain,
            'project_type' => 'php',
            'directory'    => '/public',
            'isolated'     => true,
            'username'     => $isolatedUser,
            'php_version'  => $phpVersion,
        ];

        $this->title('Creating Site');

        $siteResponse = Http::forgeServer()->post('sites', array_merge(
            $baseParams,
            ...$activePlugins->map(fn (Plugin $p) => $p->createSiteParams($baseParams))->toArray(),
        ))->json();

        $site = $siteResponse['site'];

        Http::macro(
            'forgeSite',
            fn () => Http::forge()->baseUrl("{$forgeApiUrl}/servers/{$server['id']}/sites/{$site['id']}")
        );

        while ($site['status'] !== 'installed') {
            $this->info('Waiting for site to be created... Status: ' . $site['status']);

            sleep(2);

            $site = Http::forgeSite()->get('')->json()['site'];
        }

        $baseRepoParams = [
            'provider'   => 'github',
            'repository' => $repo,
            'branch'     => $repoBranch,
            'composer'   => true,
        ];

        $this->title('Installing Repository');

        Http::forgeSite()->post(
            'git',
            array_merge(
                $baseRepoParams,
                ...$activePlugins->map(
                    fn (Plugin $p) => $p->installRepoParams($server, $site, $baseRepoParams)
                )->toArray()
            )
        );

        while ($site['repository_status'] !== 'installed') {
            $this->info(
                'Waiting for repo to be installed... '
                    . ($site['repository_status'] ? 'Status: ' . $site['repository_status'] : '')
            );

            sleep(2);

            $site = Http::forgeSite()->get('')->json()['site'];
        }

        $this->title('Updating Environment Variables');

        $this->siteEnv = Http::forgeSite()->get('env');

        ray($this->siteEnv);

        collect(array_merge(
            [
                'APP_NAME'     => $appName,
                'APP_URL'      => "http://{$domain}",
                'VITE_APP_ENV' => '${APP_ENV}',
            ],
        ))->map(
            fn ($v, $k) => $this->updateEnvKeyValue(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v)
            )
        );

        $parsedEnv = DotEnv::parse($this->siteEnv);

        $activePlugins->each(
            fn (Plugin $p) => collect($p->setEnvironmentVariables($server, $site, $parsedEnv))->each(
                fn ($v, $k) => $this->updateEnvKeyValue($k, is_array($v) ? $v[0] : $v, is_array($v))
            )
        );

        Http::forgeSite()->put('env', ['content' => $this->siteEnv]);

        $this->title('Updating Deployment Script');

        $deployScript = Http::forgeSite()->get('deployment/script');

        foreach ($activePlugins as $plugin) {
            $deployScript = $plugin->updateDeployScript($server, $site, $deployScript);
        }

        ray($deployScript);

        Http::forgeSite()->put('deployment/script', ['content' => $deployScript]);

        $this->title('Creating Deamons');

        $activePlugins->each(
            fn (Plugin $p) => collect($p->daemons($server, $site))->each(
                fn (Daemon $daemon) => Http::forgeServer()->post(
                    'daemons',
                    [
                        'command'   => $daemon->command,
                        'user'      => $daemon->user ?: $isolatedUser,
                        'directory' => $daemon->directory ?: "/home/{$isolatedUser}/{$domain}",
                    ],
                )
            )
        );

        $this->title('Creating Workers');

        $activePlugins->each(
            fn (Plugin $p) => collect($p->workers($server, $site))->each(
                fn (Worker $worker) => Http::forgeServer()->post(
                    'workers',
                    array_merge(
                        ['php_version'  => $phpVersion],
                        $worker->toArray()
                    ),
                )
            )
        );

        $this->title('Creating Scheduled Jobs');

        $activePlugins->each(
            fn (Plugin $p) => collect($p->jobs($server, $site))->each(
                fn (Job $job) => Http::forgeServer()->post(
                    'jobs',
                    array_merge(
                        ['user' => $isolatedUser],
                        $job->toArray()
                    ),
                )
            )
        );

        $this->title('Wrapping Up');

        $activePlugins->each(fn (Plugin $p) => $p->wrapUp($server, $site));

        $this->info('Site created successfully!');
        $this->info(
            "https://forge.laravel.com/servers/{$server['id']}/sites/{$site['id']}/application"
        );

        $this->newLine();

        if ($this->confirm('Deploy now?')) {
            Http::forgeSite()->post('deployment/deploy');
            $this->info('Deploying!');
        }
    }

    protected function determinePhpVersion($projectDir)
    {
        $phpVersions = collect(Http::forgeServer()->get('php')->json())->sortBy('version', SORT_REGULAR, true);
        $composerJson = file_get_contents($projectDir . '/composer.json');
        $composerJson = json_decode($composerJson, true);

        $requiredPhpVersion = $composerJson['require']['php'] ?? null;

        $phpVersion = $requiredPhpVersion ? $phpVersions->first(
            fn ($p) => Semver::satisfies(
                Str::replace('php', '', $p['binary_name']),
                $requiredPhpVersion
            )
        ) : $phpVersions->first();

        if (!$phpVersion) {
            // TODO: Offer to install it for them if it's available to install
            throw new \Exception('No PHP version on server found that matches the required version in composer.json');
        }

        return [$phpVersion['version'], $phpVersion['binary_name']];
    }

    protected function updateEnvKeyValue($key, $value, $quote = false)
    {
        $this->info("Updating .env: {$key}");

        if (
            $quote ||
            (!Str::startsWith($value, '"')
                && (Str::contains($value, ['${', ' '])
                    || Str::contains($key, ['PASSWORD'])))
        ) {
            $value = '"' . $value . '"';
        }

        if (Str::contains($this->siteEnv, "{$key}=")) {
            $this->siteEnv = preg_replace("/{$key}=.*/", "{$key}={$value}", $this->siteEnv);
            return $this->siteEnv;
        }

        $parts = collect(explode('_', $key));
        $groupingKeys = collect([]);

        if (in_array($parts->first(), ['MIX', 'VITE'])) {
            $groupingKeys->push($parts->slice(0, 2)->implode('_'));
            $parts->shift();
            $groupingKeys->push($parts->first());
        } else {
            $groupingKeys->push($parts->first());
        }

        $match = $groupingKeys->map(
            fn ($gk) => Str::matchAll("/^{$gk}_[A-Z0-9_]+=.+/m", $this->siteEnv)->last()
        )->filter()->first();

        if (!$match) {
            // Not part of any grouping, just tack it onto the end
            $this->siteEnv .=  PHP_EOL . PHP_EOL . "{$key}={$value}";
            return $this->siteEnv;
        }

        $this->siteEnv = Str::replaceFirst($match, "{$match}\n{$key}={$value}", $this->siteEnv);

        return $this->siteEnv;
    }
}
