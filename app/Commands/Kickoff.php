<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Data\InstallationData;
use Bellows\PluginManagers\InstallationManager;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Processes\CopyFiles;
use Bellows\Processes\HandleComposer;
use Bellows\Processes\HandleNpm;
use Bellows\Processes\InstallLaravel;
use Bellows\Processes\PublishVendorFiles;
use Bellows\Processes\RemoveFiles;
use Bellows\Processes\RenameFiles;
use Bellows\Processes\SetLocalEnvironmentVariables;
use Bellows\Processes\UpdateConfigFiles;
use Bellows\Processes\WrapUp;
use Illuminate\Support\Facades\File;
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

        [$configNames, $config] = $this->getConfig();

        $config = collect($config);

        $topDirectory = trim(basename($dir));

        $name = $this->ask(
            'Project name',
            Str::of($topDirectory)->replace(['-', '_'], ' ')->title()->toString(),
        );

        $url = $this->ask('URL', Str::of($name)->replace(' ', '')->slug() . '.test');

        Project::setAppName($name);
        Project::setDomain($url);

        $pluginManager->setActive($config->get('plugins', []));

        // TODO: This is a little weird to do up front? Strange, but maybe ok.
        $directoriesFromKickoffConfig = collect($configNames)
            ->map(fn ($name) => BellowsConfig::getInstance()->path('kickoff/files/' . $name))
            ->filter(fn ($dir) => is_dir($dir));

        $config->offsetSet(
            'directories-to-copy',
            $directoriesFromKickoffConfig->merge($config->get('directories-to-copy', []))->toArray()
        );

        Pipeline::send(new InstallationData($pluginManager, $config))->through([
            InstallLaravel::class,
            SetLocalEnvironmentVariables::class,
            HandleComposer::class,
            HandleNpm::class,
            PublishVendorFiles::class,
            UpdateConfigFiles::class,
            RenameFiles::class,
            CopyFiles::class,
            RemoveFiles::class,
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

    protected function getConfig(): array
    {
        // TODO: Validate configs against schema?
        $configs = collect(glob(BellowsConfig::getInstance()->path('kickoff/*.json')))
            ->map(fn ($path) => [
                'file'   => Str::replace('.json', '', basename($path)),
                'config' => File::json($path),
            ])
            ->map(fn ($item) => array_merge(
                $item,
                [
                    'name' => $item['config']['name'] ?? $item['file'],
                ],
            ))
            ->map(fn ($item) => array_merge(
                $item,
                [
                    'label' => $item['name'] . ' (' . $item['file'] . ')',
                ],
            ))
            ->sortBy('name');

        // TODO: Offer to init a config if none exist
        if ($configs->count() === 1) {
            $config = $configs->first();

            return [$config['file'], $config['config']];
        }

        $name = $this->choice(
            'What kind of project are we kicking off today?',
            $configs->pluck('label')->toArray(),
        );

        $config = $configs->firstWhere('label', $name);

        // We need to make sure we're not recursively extending configs
        $usedConfigs = [$config['file']];

        $configExtends = $config['config']['extends'] ?? false;

        while ($configExtends !== false) {
            if (in_array($configExtends, $usedConfigs)) {
                $this->error('Recursive config extending detected!');
                exit;
            }

            $usedConfigs[] = $configExtends;

            $configExtension = $configs->firstWhere('file', $configExtends);

            if (!$configExtension) {
                $this->error("Config {$configExtends} does not exist!");
                exit;
            }

            $config['config'] = $this->mergeConfigs($config['config'], $configExtension['config']);

            $configExtends = $configExtension['config']['extends'] ?? false;
        }

        return [$usedConfigs, $config['config']];
    }

    protected function mergeConfigs(array $base, array $toMerge)
    {
        $base = collect($base);
        $toMerge = collect($toMerge);

        $toMerge->each(function ($value, $key) use ($base) {
            if ($base->has($key)) {
                if (is_array($value)) {
                    // We're only interested in merge array values, keep the base config non-array values the same
                    $base->put($key, array_merge($base->get($key), $value));
                }
            } else {
                $base->put($key, $value);
            }
        });

        return $base->toArray();
    }
}
