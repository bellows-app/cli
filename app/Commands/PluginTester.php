<?php

namespace Bellows\Commands;

use Bellows\Plugins\PluginLoader;
use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class PluginTester extends Command
{
    protected $signature = 'plugin:test {--install} {--deploy} {--project.isolatedUser=bellows_tester} '
        . '{--project.repositoryUrl=bellows/tester} {--project.repositoryBranch=main} {--project.phpVersion=8.1} '
        . '{--project.phpBinary=php81} {--project.directory=tests/stubs/plugins/default} '
        . '{--project.domain=bellowstester.com} {--project.appName=Bellows Tester} {--project.secureSite=true}';

    protected $description = 'Test plugins in isolation';

    public function handle()
    {
        $dir = trim(Process::run('pwd')->output());

        if ($this->option('install')) {
            $action = 'install';
        } elseif ($this->option('deploy')) {
            $action = 'deploy';
        } else {
            $action = $this->choice('What would you like to test?', ['install', 'deploy']);
        }

        $plugin = $this->getPluginToTest($dir, $action);

        if (!$plugin) {
            $this->error('No plugin found to test');

            return;
        }

        Project::setDir($this->option('project.directory'));
        Project::setAppName($this->option('project.appName'));
        Project::setIsolatedUser($this->option('project.isolatedUser'));
        Project::setDomain($this->option('project.domain'));

        if ($action === 'install') {
            $this->installPlugin($plugin);
        } elseif ($action === 'deploy') {
            $this->deployPlugin($plugin);
        }

        // Project::setConfig(new ProjectConfig(
        //     isolatedUser: $this->option('project.isolatedUser'),
        //     repository: new Repository(
        //         $this->option('project.repositoryUrl'),
        //         $this->option('project.repositoryBranch')
        //     ),
        //     phpVersion: new PhpVersion(
        //         $this->option('project.phpVersion'),
        //         $this->option('project.phpBinary'),
        //         $this->option('project.phpVersion')
        //     ),
        //     directory: $this->option('project.directory'),
        //     domain: $this->option('project.domain'),
        //     appName: $this->option('project.appName'),
        //     secureSite: $this->option('project.secureSite'),
        // ));

        // app()->bind(ForgeServer::class, function () {
        //     return Server::from([
        //         'id'         => 123,
        //         'name'       => 'test-server',
        //         'type'       => 'php',
        //         'ip_address' => '123.123.123.123',
        //     ]);
        // });

        // app()->bind(
        //     Config::class,
        //     fn () => new Config(base_path('tests/stubs/config')),
        // );

        // $deployScript = <<<'DEPLOY'
        // cd /home/link_leap/linkleapapp.com
        // git pull origin $FORGE_SITE_BRANCH

        // $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

        // ( flock -w 10 9 || exit 1
        //     echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

        // if [ -f artisan ]; then
        //     $FORGE_PHP artisan migrate --force
        // fi
        // DEPLOY;

        $this->info("Configuring <comment>{$this->argument('plugin')}</comment> plugin...");

        $plugin = app('\\Bellows\\Plugins\\' . $this->argument('plugin'));

        $plugin->launch();

        $this->newLine(2);

        dd([
            'createSiteParams'     => $plugin->createSiteParams(
                new CreateSiteParams(
                    domain: $this->option('project.domain'),
                    projectType: 'php',
                    directory: $this->option('project.directory'),
                    isolated: true,
                    username: $this->option('project.isolatedUser'),
                    phpVersion: $this->option('project.phpVersion'),
                ),
            ),
            'installRepoParams'    => $plugin->installRepoParams(
                new InstallRepoParams(
                    'github',
                    $this->option('project.repositoryUrl'),
                    $this->option('project.repositoryBranch'),
                    composer: true,
                ),
            ),
            'environmentVariables' => $plugin->environmentVariables(),
            'updateDeployScript'   => $plugin->updateDeployScript($deployScript),
            'workers'              => $plugin->workers(),
            'jobs'                 => $plugin->jobs(),
            'daemons'              => $plugin->daemons(),
            // 'wrapUp' => $plugin->wrapUp(),
        ]);
    }

    protected function getPluginToTest(string $dir, string $action): Installable|Deployable|null
    {
        if (!File::exists($dir . '/composer.json') || !File::exists($dir . '/composer.lock') || !File::exists($dir . '/vendor/autoload.php')) {
            // We're not in a plugin directory and we don't have a plugin name, just ask for it
            $this->error("It doesn't look like you're in a plugin directory. Please head to the plugin directory and re-run command");

            return null;
        }

        $contents = File::json($dir . '/composer.json');

        if ($contents['type'] !== 'bellows-plugin') {
            $this->error("It doesn't look like you're in a plugin directory. Please head to the plugin directory and re-run command");

            return null;
        }

        require_once $dir . '/vendor/autoload.php';

        $plugins = PluginLoader::discoverInDirectories(
            [$dir],
            $action === 'install' ? Installable::class : Deployable::class,
        );

        if ($plugins->isEmpty()) {
            return null;
        }

        if ($plugins->count() === 1) {
            return $plugins->first();
        }

        $plugin = $this->choice(
            'Which plugin would you like to test?',
            $plugins->map(fn (Plugin $plugin) => $plugin->getName())->toArray()
        );

        return $plugins->first(fn (Plugin $p) => $p->getName() === $plugin);
    }

    protected function installPlugin(Installable $plugin): void
    {
        $this->info("Installing <comment>{$plugin->getName()}</comment> plugin...");

        $result = $plugin->install();

        dd($result);

        // $plugin->install(
        //     new CreateSiteParams(
        //         domain: $this->option('project.domain'),
        //         projectType: 'php',
        //         directory: $this->option('project.directory'),
        //         isolated: true,
        //         username: $this->option('project.isolatedUser'),
        //         phpVersion: $this->option('project.phpVersion'),
        //     ),
        //     new InstallRepoParams(
        //         'github',
        //         $this->option('project.repositoryUrl'),
        //         $this->option('project.repositoryBranch'),
        //         composer: true,
        //     ),
        //     $this->option('project.secureSite'),
        // );
    }

    protected function deployPlugin(Deployable $plugin): void
    {
        $this->info("Deploying <comment>{$plugin->getName()}</comment> plugin...");

        // $plugin->deploy(
        //     new CreateSiteParams(
        //         domain: $this->option('project.domain'),
        //         projectType: 'php',
        //         directory: $this->option('project.directory'),
        //         isolated: true,
        //         username: $this->option('project.isolatedUser'),
        //         phpVersion: $this->option('project.phpVersion'),
        //     ),
        //     new InstallRepoParams(
        //         'github',
        //         $this->option('project.repositoryUrl'),
        //         $this->option('project.repositoryBranch'),
        //         composer: true,
        //     ),
        //     $this->option('project.secureSite'),
        //     $this->option('project.appName'),
        //     $this->option('project.phpVersion'),
        //     $this->option('project.phpBinary'),
        //     $this->option('project.repositoryBranch'),
        //     $this->option('project.repositoryUrl'),
        //     $this->option('project.directory'),
        //     $this->option('project.isolatedUser'),
        //     $this->option('project.domain'),
        //     $this->option('project.secureSite'),
        // );
    }
}
