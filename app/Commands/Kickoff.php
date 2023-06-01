<?php

namespace Bellows\Commands;

use Bellows\Artisan;
use Bellows\Config\BellowsConfig;
use Bellows\Data\PhpVersion;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\PluginManagers\InstallationManager;
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

        // TODO: This is bad. This should not be done. But for now, ok. Maybe NewProject? Not sure.
        Project::setConfig(
            new ProjectConfig(
                isolatedUser: '',
                repository: new Repository('', ''),
                phpVersion: new PhpVersion('', '', ''),
                directory: $dir,
                domain: $url,
                appName: $name,
                secureSite: false,
            )
        );

        $pluginManager->setActive($config['plugins'] ?? []);

        $this->step('Installing Laravel');

        Process::runWithOutput('composer create-project laravel/laravel .');

        File::put($dir . '/README.md', "# {$name}");

        $this->step('Environment Variables');

        collect(
            array_merge(
                $pluginManager->environmentVariables([
                    'APP_NAME'      => Project::config()->appName,
                    'APP_URL'       => 'http://' . Project::config()->domain,
                    'VITE_APP_ENV'  => '${APP_ENV}',
                    'VITE_APP_NAME' => '${APP_NAME}',
                ]),
                $config['env'] ?? [],
            )
        )->each(fn ($value, $key) => Project::env()->update($key, $value));

        File::put($dir . '/.env', Project::env()->toString());

        $this->step('Composer Packages');

        $pluginManager->composerPackages($config['composer'] ?? [])->whenNotEmpty(
            fn ($packages) =>  Composer::require($packages->toArray()),
        );

        $pluginManager->composerDevPackages($config['composer-dev'] ?? [])->whenNotEmpty(
            fn ($packages) => Composer::require($packages->toArray(), true),
        );

        $this->step('NPM Packages');

        $pluginManager->npmPackages($config['npm'] ?? [])->whenNotEmpty(
            fn ($packages) => Npm::install($packages->toArray())
        );

        $pluginManager->npmDevPackages($config['npm-dev'] ?? [])->whenNotEmpty(
            fn ($packages) => Npm::install($packages->toArray(), true),
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

        $this->step('Publish Tags');

        collect($pluginManager->publishTags($config['publish-tags'] ?? []))->each(
            fn ($t) => Process::runWithOutput(
                Artisan::local("vendor:publish --tag={$t}"),
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
            ->map(fn ($name) => BellowsConfig::getInstance()->path('kickoff/' . $name))
            ->filter(fn ($dir) => is_dir($dir))
            ->merge($pluginManager->directoriesToCopy())
            ->whenNotEmpty(function ($toCopy) {
                $this->step('Copying Files');

                $toCopy->each(function ($src) {
                    Process::run("cp -R {$src}/* " . Project::config()->directory);
                    $this->info('Copied files from ' . $src);
                });
            });

        collect($config['remove-files'] ?? [])->whenNotEmpty(function ($toRemove) {
            $this->step('Removing Files');

            $toRemove
                ->map(fn ($file) => Project::config()->directory . '/' . $file)
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

        // exec('echo ".yarn" >> .gitignore');

        // Add repo to GitHub Desktop?
        // exec('github .');
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
