<?php

namespace App\Commands;

use App\Bellows\Config;
use App\Bellows\Console;
use App\Bellows\Data\ForgeServer;
use App\Bellows\Data\ProjectConfig;
use App\Bellows\Dns\DnsFactory;
use App\Bellows\Dns\DnsProvider;
use App\Bellows\Env;
use App\Bellows\PluginManager;
use Composer\Semver\Semver;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;

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
    protected $description = 'Create the current repo as a site on a Forge server';

    protected $defaultLongProcessMessages = [
        3  => 'One moment...',
        7  => 'Almost done...',
        11 => 'Wrapping up...',
    ];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Config $config, Console $console, PluginManager $pluginManager)
    {
        $console->setInput($this->input);
        $console->setOutput($this->output);

        $forgeApiUrl = 'https://forge.laravel.com/api/v1';
        $apiHost = parse_url($forgeApiUrl, PHP_URL_HOST);

        $apiConfigKey = 'apiCredentials.' . str_replace('.', '-', $apiHost) . '.default';

        $startingNewLine = true;

        if (!$config->get($apiConfigKey)) {
            $this->newLine();
            $this->info('Looks like we need a Forge API token, you can get one here:');
            $this->info('https://forge.laravel.com/user-profile/api');

            $token = $this->secret('Forge API Token');

            $config->set($apiConfigKey, $token);

            $startingNewLine = false;
        }

        $dir = rtrim(getcwd(), '/');

        if (!file_exists($dir . '/.env')) {
            if ($startingNewLine) {
                $this->newLine();
            }

            $this->error('No .env file found! Are you in the correct directory?');
            $this->info('Bellows works best when it has access to an .env file.');
            $this->newLine();
            exit;
        }

        Http::macro(
            'forge',
            fn () => Http::baseUrl($forgeApiUrl)
                ->withToken($config->get($apiConfigKey))
                ->acceptJson()
                ->asJson()
                ->retry(
                    3,
                    100,
                    function (
                        Exception $exception,
                        PendingRequest $request
                    ) {
                        if ($exception instanceof RequestException && $exception->response->status() === 429) {
                            sleep($exception->response->header('retry-after') + 1);

                            return true;
                        }

                        return false;
                    }
                )
        );

        $server = $this->getServer($startingNewLine);

        App::instance(ForgeServer::class, ForgeServer::from($server));

        Http::macro(
            'forgeServer',
            fn () =>  Http::forge()->baseUrl("{$forgeApiUrl}/servers/{$server['id']}")
        );

        $localEnv = new Env(file_get_contents($dir . '/.env'));

        $host = parse_url($localEnv->get('APP_URL'), PHP_URL_HOST);

        $appName      = $this->ask('App Name', $localEnv->get('APP_NAME'));
        $domain       = $this->ask('Domain', Str::replace('.test', '.com', $host));

        $existingDomain = $this->withSpinner(
            title: 'Checking for existing domain on server',
            task: fn () => collect(Http::forgeServer()->get('sites')->json()['sites'])
                ->first(fn ($site) => $site['name'] === $domain),
            successDisplay: fn ($result) => $result ? '✗' : '✓',
        );

        if ($existingDomain) {
            $this->newLine();
            $this->error('Domain already exists on server!');

            if ($this->confirm('View existing site in Forge?', true)) {
                exec("open https://forge.laravel.com/servers/{$server['id']}/sites/{$existingDomain['id']}");
            }

            exit;
        }

        $isolatedUser = $this->ask(
            'Isolated User',
            Str::of($host)->replace('.test', '')->replace('.', '_')->toString()
        );

        [$repo, $repoBranch] = $this->getGitInfo($dir, $domain);

        $dnsProvider = DnsFactory::fromDomain($domain);

        if (!$dnsProvider) {
            $this->warn('Unsupported DNS provider for ' . $domain);
        } else {
            $this->info('Detected DNS provider: ' . $dnsProvider->getName());
            $dnsProvider->setCredentials();
        }

        App::instance(DnsProvider::class, $dnsProvider);

        [$phpVersion, $phpVersionBinary] = $this->determinePhpVersion($dir);

        App::instance(ProjectConfig::class, new ProjectConfig(
            isolatedUser: $isolatedUser,
            repositoryUrl: $repo,
            repositoryBranch: $repoBranch,
            phpVersion: $phpVersion,
            phpBinary: $phpVersionBinary,
            projectDirectory: $dir,
            domain: $domain,
            appName: $appName,
        ));

        $this->info('Loading plugins...');

        App::call([$pluginManager, 'setActive']);

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
            ...App::call([$pluginManager, 'createSiteParams'], ['params' => $baseParams])
        );

        $site = $this->withSpinner(
            title: 'Creating Site',
            task: function () use ($createSiteParams) {
                $siteResponse = Http::forgeServer()->post('sites', $createSiteParams)->json();

                $site = $siteResponse['site'];

                while ($site['status'] !== 'installed') {
                    sleep(2);

                    $site = Http::forgeServer()->get("sites/{$site['id']}")->json()['site'];
                }

                return $site;
            },
            longProcessMessages: $this->defaultLongProcessMessages,
        );

        Http::macro(
            'forgeSite',
            fn () => Http::forge()->baseUrl("{$forgeApiUrl}/servers/{$server['id']}/sites/{$site['id']}")
        );

        $baseRepoParams = [
            'provider'   => 'github',
            'repository' => $repo,
            'branch'     => $repoBranch,
            'composer'   => true,
        ];

        $installRepoParams = array_merge(
            $baseRepoParams,
            ...App::call([$pluginManager, 'installRepoParams'], ['baseParams' => $baseRepoParams])
        );

        $this->withSpinner(
            title: 'Installing Repository',
            task: function () use ($installRepoParams, $site) {
                Http::forgeSite()->post('git', $installRepoParams);

                while ($site['repository_status'] !== 'installed') {
                    sleep(2);

                    $site = Http::forgeSite()->get('')->json()['site'];
                }
            },
            longProcessMessages: $this->defaultLongProcessMessages,
        );

        $this->title('Updating Environment Variables');

        $siteEnv = new Env(Http::forgeSite()->get('env'));

        ray($siteEnv);

        $updatedEnvValues = collect(array_merge(
            [
                'APP_NAME'     => $appName,
                'APP_URL'      => "http://{$domain}",
                'VITE_APP_ENV' => '${APP_ENV}',
            ],
            App::call([$pluginManager, 'setEnvironmentVariables']),
        ));

        $this->info('Setting the following environment variables:');

        $updatedEnvValues->each(
            fn ($v, $k) => $this->info($k)
        );

        $updatedEnvValues->map(
            fn ($v, $k) => $siteEnv->update(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        $inLocalButNotInRemote = collect(array_keys($localEnv->all()))->diff(array_keys($siteEnv->all()))->values();

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
            title: 'Updating Environment Variables',
            task: fn () => Http::forgeSite()->put('env', ['content' => $siteEnv->toString()]),
        );

        $this->title('Updating Deployment Script');

        $deployScript = (string) Http::forgeSite()->get('deployment/script');
        $deployScript = App::call([$pluginManager, 'updateDeployScript'], ['deployScript' => $deployScript]);

        Http::forgeSite()->put('deployment/script', ['content' => $deployScript]);

        $this->title('Creating Deamons');
        App::call([$pluginManager, 'daemons']);

        $this->title('Creating Workers');
        App::call([$pluginManager, 'workers']);

        $this->title('Creating Scheduled Jobs');
        App::call([$pluginManager, 'jobs']);

        $this->title('Wrapping Up');
        App::call([$pluginManager, 'wrapUp']);

        $this->info('Site created successfully!');
        $this->info(
            "https://forge.laravel.com/servers/{$server['id']}/sites/{$site['id']}/application"
        );

        $this->newLine();

        if ($this->confirm('Open site in Forge?', true)) {
            exec("open https://forge.laravel.com/servers/{$server['id']}/sites/{$site['id']}/application");
        }
    }

    protected function getServer(bool $startingNewLine)
    {
        $servers = collect(
            Http::forge()->get('servers')->json()['servers']
        )->filter(fn ($s) => !$s['revoked'])->values();

        if ($servers->isEmpty()) {
            if ($startingNewLine) {
                $this->newLine();
            }

            $this->error('No servers found!');
            $this->error('Provision a server on Forge first then come on back: https://forge.laravel.com/servers');
            $this->newLine();
            exit;
        }

        if ($servers->count() === 1) {
            $server = $servers->first();

            if ($startingNewLine) {
                $this->newLine();
            }

            $this->info("Found only one server, auto-selecting: <comment>{$server['name']}</comment>");

            return $server;
        }

        $serverName = $this->choice(
            'Which server?',
            $servers->pluck('name')->sort()->values()->toArray()
        );

        return $servers->first(fn ($s) => $s['name'] === $serverName);
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

        $repoUrl = exec('git config --get remote.origin.url');

        $branches = collect(explode(PHP_EOL, shell_exec('git branch -a')))->map(fn ($b) => trim($b))->filter();

        $mainBranch = Str::of($branches->first(fn ($b) => Str::startsWith($b, '*')))->replace('*', '')->trim();

        $devBranch = $branches->first(fn ($b) => in_array($b, ['develop', 'dev', 'development']));

        $branchChoices = $branches->map(
            fn ($b) => Str::of($b)->replace(['*', 'remotes/origin/'], '')->trim()->toString()
        )->filter(fn ($b) => !Str::startsWith($b, 'HEAD ->'))->filter()->values();

        $defaultRepo = collect(explode(':', $repoUrl))->map(
            fn ($p) => Str::replace('.git', '', $p)
        )->last();


        $repo = $this->ask('Repo', $defaultRepo);

        if ($repo === $defaultRepo) {
            // Only offer up possible branches if this is the repo we thought it was
            $repoBranch = $this->anticipate(
                'Repo Branch',
                $branchChoices,
                Str::contains($domain, 'dev.') ? $devBranch : $mainBranch
            );
        } else {
            $repoBranch = $this->ask('Repository Branch');
        }

        return [$repo, $repoBranch];
    }

    protected function determinePhpVersion($projectDir)
    {
        $composerJson = file_get_contents($projectDir . '/composer.json');
        $composerJson = json_decode($composerJson, true);
        $requiredPhpVersion = $composerJson['require']['php'] ?? null;

        $phpVersion = $this->withSpinner(
            title: 'Determining PHP Version',
            task: function () use ($requiredPhpVersion,) {
                $phpVersions = collect(Http::forgeServer()->get('php')->json())->sortByDesc('version');

                return $requiredPhpVersion ? $phpVersions->first(
                    fn ($p) => Semver::satisfies(
                        Str::replace('php', '', $p['binary_name']),
                        $requiredPhpVersion
                    )
                ) : $phpVersions->first();
            },
            successDisplay: fn ($result) => $result['binary_name'] ?? '✗',
        );

        if ($phpVersion) {
            return [$phpVersion['version'], $phpVersion['binary_name']];
        }

        $available = collect([
            'php82' => '8.2',
            'php81' => '8.1',
            'php80' => '8.0',
            'php74' => '7.4',
            'php73' => '7.3',
            'php72' => '7.2',
            'php71' => '7.1',
            'php70' => '7.0',
            'php56' => '5.6',
        ]);

        $toInstall = $available->first(
            fn ($v, $k) => Semver::satisfies($v, $requiredPhpVersion)
        );

        if (!$toInstall || !$this->confirm("PHP {$toInstall} is required, but not installed. Install it now?", true)) {
            throw new \Exception('No PHP version on server found that matches the required version in composer.json');
        }

        $phpVersion = $this->installPHPVersion($toInstall, $available->search($toInstall));

        return [$phpVersion['version'], $phpVersion['binary_name']];
    }

    protected function installPHPVersion($name, $version)
    {
        return $this->withSpinner(
            title: 'Installing PHP on server',
            task: function () use ($version) {
                Http::forgeServer()->post('php', ['version' => $version]);

                do {
                    $phpVersion = collect(Http::forgeServer()->get('php')->json())->first(
                        fn ($p) => $p['version'] === $version
                    );
                } while ($phpVersion['status'] !== 'installed');

                return $phpVersion;
            },
            successDisplay: fn ($result) => $result['binary_name'] ?? '✗',
            longProcessMessages: $this->defaultLongProcessMessages,
        );
    }
}