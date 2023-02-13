<?php

namespace App\Commands;

use App\Bellows\Config;
use App\Bellows\Console;
use App\Bellows\Data\ForgeServer;
use App\Bellows\Data\ProjectConfig;
use App\Bellows\Dns\DnsFactory;
use App\Bellows\Dns\DnsProvider;
use App\Bellows\PluginManager;
use Composer\Semver\Semver;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\App;
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
    public function handle(Config $config, Console $console, PluginManager $pluginManager)
    {
        $console->setInput($this->input);
        $console->setOutput($this->output);

        if (!$config->get('forge.token')) {
            $this->info('Looks like we need a Forge token, you can get one here:');
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

        // TODO: check if this is a git repo
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

        App::instance(ForgeServer::class, ForgeServer::from($server));

        Http::macro(
            'forgeServer',
            fn () =>  Http::forge()->baseUrl("{$forgeApiUrl}/servers/{$server['id']}")
        );

        $localEnv = Dotenv::parse(file_get_contents($dir . '/.env'));

        $host = parse_url($localEnv['APP_URL'], PHP_URL_HOST);

        $appName      = $this->ask('App Name', $localEnv['APP_NAME']);
        $domain       = $this->ask('Domain', Str::replace('.test', '.com', $host));
        $isolatedUser = $this->ask(
            'Isolated User',
            Str::of($host)->replace('.test', '')->replace('.', '_')->toString()
        );
        $repo         = $this->ask('Repo', $defaultRepo);
        $repoBranch   = $this->anticipate(
            'Repo Branch',
            $branchChoices,
            Str::contains($domain, 'dev.') ? $devBranch : $mainBranch
        );

        $dnsProvider = DnsFactory::fromDomain($domain);

        if (!$dnsProvider) {
            $this->warn('Unsupported DNS provider for ' . $domain);
        } else {
            $this->info('Detected DNS provider: ' . $dnsProvider->getName());
            $dnsProvider->setCredentials();
        }

        ray($dnsProvider);

        App::instance(DnsProvider::class, $dnsProvider);

        $this->info('Configuring PHP version...');

        [$phpVersion, $phpVersionBinary] = $this->determinePhpVersion($dir);

        $this->line('PHP Version: ' . $phpVersionBinary);

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

        $this->title('Creating Site');

        $siteResponse = Http::forgeServer()->post('sites', array_merge(
            $baseParams,
            ...App::call([$pluginManager, 'createSiteParams'], ['params' => $baseParams])
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
                ...App::call([$pluginManager, 'installRepoParams'], ['baseParams' => $baseRepoParams])
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

        $newEnv = App::call([$pluginManager, 'setEnvironmentVariables'], ['parsedEnv' => $parsedEnv]);

        ray($newEnv);

        collect($newEnv)->each(fn ($v, $k) => $this->updateEnvKeyValue($k, $v));

        Http::forgeSite()->put('env', ['content' => $this->siteEnv]);

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

        if ($this->confirm('Open site in Forge?')) {
            exec("open https://forge.laravel.com/servers/{$server['id']}/sites/{$site['id']}/application");
        }
    }

    protected function determinePhpVersion($projectDir)
    {
        $phpVersions = collect(Http::forgeServer()->get('php')->json())->sortByDesc('version');

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
        }

        return [$phpVersion['version'], $phpVersion['binary_name']];
    }

    protected function installPHPVersion($name, $version)
    {
        $this->info("Installing PHP {$name} (this will take a minute or two)...");
        Http::forgeServer()->post('php', ['version' => $version]);

        $this->newLine();

        $bar = $this->output->createProgressBar(0);
        $bar->start();

        do {
            $phpVersion = collect(Http::forgeServer()->get('php')->json())->first(
                fn ($p) => $p['version'] === $version
            );
            $bar->advance();
            sleep(2);
        } while ($phpVersion['status'] !== 'installed');

        $bar->finish();

        $this->newLine();

        $this->info("PHP {$name} installed successfully!");

        return $phpVersion;
    }

    // TODO: This probably.... doesn't belong here. Feels funny. Move it at some point.
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
