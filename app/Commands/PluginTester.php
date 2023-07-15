<?php

namespace Bellows\Commands;

use Bellows\Plugins\PluginLoader;
use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Data\PhpVersion;
use Bellows\PluginSdk\Data\Repository;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class PluginTester extends Command
{
    protected $signature = 'plugin:test {--install} {--deploy} {--isolatedUser=bellows_tester} '
        . '{--repositoryUrl=bellows/tester} {--repositoryBranch=main} {--phpVersion=8.1} '
        . '{--directory=tests/stubs/plugins/default} {--domain=bellowstester.com} '
        . '{--appName="Bellows Tester"} {--secureSite=true}';

    protected $description = 'Test plugins in isolation';

    public function handle()
    {
        $this->newLine();
        $this->alert('Remember: This is testing your *actual* plugin against any APIs you are connected to!');

        $dir = trim(Process::run('pwd')->output());

        $action = match (true) {
            $this->option('install') => 'install',
            $this->option('deploy')  => 'deploy',
            default                  => $this->choice('What would you like to test?', ['install', 'deploy']),
        };

        $plugin = $this->getPluginToTest($dir, $action);

        if (!$plugin) {
            $this->error('No plugin found to test');

            return;
        }

        Project::setDir($this->option('directory'));
        Project::setAppName($this->option('appName'));
        Project::setIsolatedUser($this->option('isolatedUser'));
        Project::setDomain($this->option('domain'));
        Project::setRepo(new Repository(
            $this->option('repositoryUrl'),
            $this->option('repositoryBranch')
        ));
        Project::setPhpVersion(
            new PhpVersion(
                'php' . str_replace('.', '', $this->option('phpVersion')),
                'php' . $this->option('phpVersion'),
                'PHP ' . $this->option('phpVersion')
            ),
        );
        Project::setSiteIsSecure($this->option('secureSite') === 'true' || $this->option('secureSite') === '1');

        if ($action === 'install') {
            $this->installPlugin($plugin);
        } elseif ($action === 'deploy') {
            $this->deployPlugin($plugin);
        }
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
        $result = $plugin->install();
        dd($result);
    }

    protected function deployPlugin(Deployable $plugin): void
    {
        $result = $plugin->deploy();
        dd($result);
    }
}
