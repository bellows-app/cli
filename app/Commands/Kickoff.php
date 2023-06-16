<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Config\KickoffConfig;
use Bellows\Data\InstallationData;
use Bellows\PluginManagers\InstallationManager;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Processes\CopyFiles;
use Bellows\Processes\HandleComposer;
use Bellows\Processes\HandleGit;
use Bellows\Processes\HandleNpm;
use Bellows\Processes\InstallLaravel;
use Bellows\Processes\PublishVendorFiles;
use Bellows\Processes\RemoveFiles;
use Bellows\Processes\RenameFiles;
use Bellows\Processes\RunCommands;
use Bellows\Processes\SetLocalEnvironmentVariables;
use Bellows\Processes\UpdateConfigFiles;
use Bellows\Processes\WrapUp;
use Illuminate\Support\Facades\Pipeline;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class Kickoff extends Command
{
    protected $signature = 'kickoff';

    protected $description = 'Start a new Laravel project according to your specifications';

    public function handle(InstallationManager $pluginManager)
    {
        if (Process::run('ls -A .')->output() !== '') {
            $this->newLine();
            $this->error('Directory is not empty. Kickoff only works with a fresh directory.');
            $this->newLine();

            return;
        }

        $this->warn('');
        $this->info("🚀 Let's create a new project! This is exciting.");
        $this->newLine();

        $dir = trim(Process::run('pwd')->output());

        Project::setDir($dir);

        $this->comment("Creating project in {$dir}");

        $config = $this->getConfig();

        if (!$config->isValid()) {
            return Command::FAILURE;
        }

        $topDirectory = trim(basename($dir));

        $name = $this->ask(
            'Project name',
            Str::of($topDirectory)->replace(['-', '_'], ' ')->title()->toString(),
        );

        $url = $this->ask('URL', Str::of($name)->replace(' ', '')->slug() . '.test');

        Project::setAppName($name);
        Project::setDomain($url);

        $pluginManager->setActive($config->get('plugins', []));

        Pipeline::send(new InstallationData($pluginManager, $config))->through([
            InstallLaravel::class,
            SetLocalEnvironmentVariables::class,
            HandleComposer::class,
            HandleNpm::class,
            RunCommands::class,
            PublishVendorFiles::class,
            UpdateConfigFiles::class,
            RenameFiles::class,
            CopyFiles::class,
            RemoveFiles::class,
            HandleGit::class,
            WrapUp::class,
        ]);

        $this->step('Consider yourself kicked off!');

        $this->comment(
            Project::env()->get('APP_NAME') . ' <info>awaits</info>: ' . Project::env()->get('APP_URL')
        );

        $this->newLine();

        // TODO:
        // open in editor
    }

    protected function getConfig(): KickoffConfig
    {
        $configs = collect(glob(BellowsConfig::getInstance()->path('kickoff/*.json')))
            ->map(fn ($path) => new KickoffConfig($path))
            ->sortBy(fn (KickoffConfig $config) => $config->displayName());

        // TODO: Offer to init a config if none exist
        if ($configs->count() === 1) {
            return $configs->first();
        }

        $name = $this->choice(
            'What kind of project are we kicking off today?',
            $configs->map(fn (KickoffConfig $c) => $c->displayName())->toArray(),
        );

        return $configs->first(fn (KickoffConfig $c) => $c->displayName() === $name);
    }
}
