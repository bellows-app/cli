<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Config\KickoffConfig;
use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\Plugins\Manager;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Processes\CopyFiles;
use Bellows\Processes\HandleComposer;
use Bellows\Processes\HandleGit;
use Bellows\Processes\HandleNpm;
use Bellows\Processes\InstallLaravel;
use Bellows\Processes\PublishVendorFiles;
use Bellows\Processes\RemoveFiles;
use Bellows\Processes\RenameFiles;
use Bellows\Processes\RunInstallationCommands;
use Bellows\Processes\RunWrapUpCommands;
use Bellows\Processes\SetLocalEnvironmentVariables;
use Bellows\Processes\UpdateConfigFiles;
use Bellows\Processes\WrapUpInstallation;
use Bellows\ProcessManagers\InstallationManager;
use Bellows\Util\Editor;
use Illuminate\Support\Facades\Pipeline;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class Install extends Command
{
    protected $signature = 'install {query?}';

    protected $description = 'Use a Bellows plugin to install a package or setup something in your existing app.';

    public function handle(InstallationManager $installationManager, Manager $pluginManager)
    {
        $pluginManager->installDefaults();

        $this->warn('');
        $this->info("🔧 Let's get your plugin setup!");
        $this->newLine();

        $dir = trim(Process::run('pwd')->output());

        Project::setDir($dir);

        $localEnv = Project::env();

        Project::setAppName($localEnv->get('APP_NAME'));
        Project::setDomain($localEnv->get('APP_URL'));

        $this->comment('Gathering information...');
        $this->newLine();

        $toInstall = $this->getPluginToInstall($pluginManager, $this->argument('query'));

        $installationManager->setActive([$toInstall]);

        $config = new KickoffConfig(base_path('stubs/empty-kickoff.json'));

        Pipeline::send(new InstallationData($installationManager, $config))->through([
            SetLocalEnvironmentVariables::class,
            HandleComposer::class,
            HandleNpm::class,
            RunInstallationCommands::class,
            PublishVendorFiles::class,
            UpdateConfigFiles::class,
            RenameFiles::class,
            CopyFiles::class,
            RemoveFiles::class,
            HandleGit::class,
            WrapUpInstallation::class,
            RunWrapUpCommands::class,
        ])->then(fn () => null);

        $this->step('All set!');
    }

    protected function getPluginToInstall(Manager $pluginManager, ?string $query = null): string
    {
        $plugins = $pluginManager->installed();

        if ($query) {
            $filtered = $plugins->filter(
                fn ($plugin) => Str::contains($plugin, strtolower($query))
            )->values();

            $plugins = $filtered->isNotEmpty() ? $filtered : $plugins;
        }

        if ($plugins->count() === 1) {
            if ($this->confirm('Install <comment>' . $plugins->first() . '</comment>?', true)) {
                return $plugins->first();
            }

            return $this->getPluginToInstall($pluginManager);
        }

        return $this->choice('Which plugin would you like to install', $plugins->toArray());
    }
}
