<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Git\Git;
use Bellows\PluginManagers\InstallationManager;
use Bellows\PluginSdk\Facades\Artisan;
use Bellows\PluginSdk\Facades\Composer;
use Bellows\PluginSdk\Facades\Npm;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Util\ConfigHelper;
use Bellows\Util\RawValue;
use Illuminate\Support\Facades\File;
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

        $topDirectory = trim(basename($dir));

        $name = $this->ask(
            'Project name',
            Str::of($topDirectory)->replace(['-', '_'], ' ')->title()->toString(),
        );

        $url = $this->ask('URL', Str::of($name)->replace(' ', '')->slug() . '.test');

        Project::setAppName($name);
        Project::setDomain($url);

        $pluginManager->setActive($config['plugins'] ?? []);

        $this->step('Installing Laravel');

        Process::runWithOutput('composer create-project laravel/laravel .');

        File::put($dir . '/README.md', "# {$name}");

        $this->step('Environment Variables');

        collect(
            array_merge(
                $pluginManager->environmentVariables([
                    'APP_NAME'      => Project::appName(),
                    'APP_URL'       => 'http://' . Project::domain(),
                    'VITE_APP_ENV'  => '${APP_ENV}',
                    'VITE_APP_NAME' => '${APP_NAME}',
                ]),
                $config['env'] ?? [],
            )
        )->each(fn ($value, $key) => Project::env()->set($key, $value));

        File::put($dir . '/.env', (string) Project::env());

        $this->step('Composer Packages');

        // We're doing this separately from the config below so we dont have to merge recursively
        $pluginManager->composerScripts()->whenNotEmpty(
            fn ($scripts) => $scripts->each(fn ($commands, $event) => Composer::addScript($event, $commands)),
        );

        collect($config['composer-scripts'] ?? [])->whenNotEmpty(
            fn ($scripts) => $scripts->each(fn ($commands, $event) => Composer::addScript($event, $commands)),
        );

        $pluginManager->allowedComposerPlugins($config['composer-allow-plugins'] ?? [])->whenNotEmpty(
            fn ($packages) => Composer::allowPlugin($packages->toArray()),
        );

        $pluginManager->composerPackages($config['composer'] ?? [])->whenNotEmpty(
            function ($packages) {
                [$withFlags, $noFlags] = $packages->partition(fn ($package) => Str::contains($package, ' --'));

                if ($noFlags->isNotEmpty()) {
                    Composer::require($noFlags->toArray());
                }

                $withFlags->each(fn ($package) => Composer::require($package));
            }
        );

        $pluginManager->composerDevPackages($config['composer-dev'] ?? [])->whenNotEmpty(
            function ($packages) {
                [$withFlags, $noFlags] = $packages->partition(fn ($package) => Str::contains($package, ' --'));

                if ($noFlags->isNotEmpty()) {
                    Composer::requireDev($noFlags->toArray());
                }

                $withFlags->each(fn ($package) => Composer::requireDev($package));
            }
        );

        $this->step('NPM Packages');

        $pluginManager->npmPackages($config['npm'] ?? [])->whenNotEmpty(
            fn ($packages) => Npm::install($packages->toArray())
        );

        $pluginManager->npmDevPackages($config['npm-dev'] ?? [])->whenNotEmpty(
            fn ($packages) => Npm::install($packages->toArray(), true),
        );

        $pluginManager->gitIgnore($config['git-ignore'] ?? [])->whenNotEmpty(
            fn ($files) => Git::ignore($files)
        );

        collect($pluginManager->commands($config['commands'] ?? []))->map(function ($command) {
            if ($command instanceof RawValue) {
                return (string) $command;
            }

            if (Str::startsWith($command, 'php')) {
                return $command;
            }

            return Artisan::local($command);
        })->each(fn ($command) => Process::runWithOutput($command));

        $this->step('Vendor Publish');

        $fromConfig = collect($config['publish-tags'] ?? [])->map(fn ($tag) => compact('tag'))->merge(
            collect($config['publish-providers'] ?? [])->map(fn ($provider) => compact('provider')),
        )->merge($config['vendor-publish'] ?? []);

        collect($pluginManager->vendorPublish())->map(
            fn ($t) => collect($t)->filter()->map(
                fn ($value, $key) => sprintf('--%s="%s"', $key, $value)
            )->implode(' ')
        )->each(
            fn ($params) => Process::runWithOutput(
                Artisan::local("vendor:publish {$params}"),
            ),
        );

        $this->step('Update Config Files');

        $aliasesFromConfig = array_merge(
            $config['facades'] ?? [],
            $config['aliases'] ?? [],
        );

        collect($pluginManager->aliasesToRegister($aliasesFromConfig))->each(
            fn ($value, $key) => (new ConfigHelper)->update(
                "app.aliases.{$key}",
                Str::finish($value, '::class'),
            )
        );

        collect($pluginManager->serviceProvidersToRegister($config['service-providers'] ?? []))->each(
            fn ($provider) => (new ConfigHelper)->append(
                'app.providers',
                Str::finish($provider, '::class'),
            ),
        );

        collect($pluginManager->updateConfig($config['config'] ?? []))->each(
            fn ($value, $key) => (new ConfigHelper)->update($key, $value)
        );

        collect($config['rename-files'] ?? [])->whenNotEmpty(function ($toRename) {
            $this->step('Renaming Files');

            $toRename->each(function ($newFile, $oldFile) {
                if (File::missing(Project::path($oldFile))) {
                    $this->warn("File {$oldFile} does not exist, skipping.");

                    return;
                }

                $this->info("{$oldFile} -> {$newFile}");
                File::move(Project::path($oldFile), Project::path($newFile));
            });
        });

        collect($configNames)
            ->map(fn ($name) => BellowsConfig::getInstance()->path('kickoff/files/' . $name))
            ->filter(fn ($dir) => is_dir($dir))
            ->merge($pluginManager->directoriesToCopy())
            ->whenNotEmpty(function ($toCopy) {
                $this->step('Copying Files');

                $toCopy->each(function ($src) {
                    Process::run("cp -R {$src}/* " . Project::dir());
                    $this->info('Copied files from ' . $src);
                });
            });

        collect($config['remove-files'] ?? [])->whenNotEmpty(function ($toRemove) {
            $this->step('Removing Files');

            $toRemove
                ->map(fn ($file) => Project::dir() . '/' . $file)
                ->filter(function ($file) {
                    if (File::exists($file)) {
                        return true;
                    }

                    $this->warn("File {$file} does not exist, skipping.");

                    return false;
                })
                ->each(function ($file) {
                    $this->info($file);
                    File::delete($file);
                });
        });

        $pluginManager->wrapUp();

        $this->step('Consider yourself kicked off!');

        $this->comment(
            Project::env()->get('APP_NAME') . ' <info>awaits</info>: ' . Project::env()->get('APP_URL')
        );

        // TODO:
        // npm run dev
        // open in editor

        $this->newLine();
    }

    protected function getConfig(): array
    {
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
