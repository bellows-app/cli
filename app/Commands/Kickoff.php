<?php

namespace Bellows\Commands;

use Bellows\Artisan;
use Bellows\Data\PhpVersion;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\PluginManagerInterface;
use Bellows\Util\ConfigHelper;
use Bellows\Util\RawValue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class Kickoff extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'kickoff';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start a new Laravel project according to your specifications';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(PluginManagerInterface $pluginManager)
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

        [$configFiles, $config] = $this->getConfig();

        $topDirectory = trim(basename($dir));

        $name = $this->ask(
            'Project name',
            Str::of($topDirectory)->replace(['-', '_'], ' ')->title()->toString(),
        );
        // TODO: See if they have Valet? Maybe they don't want vanity urls?
        $url = $this->ask('URL', Str::of($name)->replace(' ', '')->slug() . '.test');

        // TODO: This is bad. This should not be done. But for now, ok.
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

        $pluginManager->setActiveForInstall($config['plugins'] ?? []);

        $this->step('Installing Laravel');

        Process::runWithOutput('composer create-project laravel/laravel .');

        file_put_contents($dir . '/README.md', "# {$name}");

        $this->step('Environment Variables');

        $updatedEnvValues = collect(array_merge(
            [
                'APP_NAME'     => Project::config()->appName,
                'APP_URL'      => 'http://' . Project::config()->domain,
                'VITE_APP_ENV' => '${APP_ENV}',
            ],
            $pluginManager->environmentVariables(),
            $config['env'] ?? [],
        ));

        $updatedEnvValues->each(
            fn ($v, $k) => Project::env()->update(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        file_put_contents($dir . '/.env', Project::env()->toString());

        $this->step('Composer Packages');

        collect($pluginManager->composerPackagesToInstall())
            ->concat($config['composer'] ?? [])
            ->unique()
            ->values()
            ->tap(
                function ($packages) {
                    if ($packages->isNotEmpty()) {
                        Composer::require($packages->toArray());
                    }
                }
            );

        collect($pluginManager->composerDevPackagesToInstall())
            ->concat($config['composer-dev'] ?? [])
            ->unique()
            ->values()
            ->tap(
                function ($packages) {
                    if ($packages->isNotEmpty()) {
                        Composer::require($packages->toArray(), true);
                    }
                }
            );

        $this->step('NPM Packages');

        collect($pluginManager->npmPackagesToInstall())
            ->concat($config['npm'] ?? [])
            ->unique()
            ->values()
            ->tap(
                function ($packages) {
                    if ($packages->isNotEmpty()) {
                        Npm::install($packages->toArray());
                    }
                }
            );

        collect($pluginManager->npmDevPackagesToInstall())
            ->concat($config['npm-dev'] ?? [])
            ->unique()
            ->values()
            ->tap(
                function ($packages) {
                    if ($packages->isNotEmpty()) {
                        Npm::install($packages->toArray(), true);
                    }
                }
            );

        collect($pluginManager->installCommands())
            ->unique()
            ->values()
            ->map(function ($command) {
                if ($command instanceof RawValue) {
                    return (string) $command;
                }

                if (Str::startsWith($command, 'php')) {
                    return $command;
                }

                return Artisan::local($command);
            })->each(
                fn ($command) => Process::runWithOutput($command),
            );

        $this->step('Publish Tags');

        collect($pluginManager->publishTags())
            ->concat($config['publish-tags'] ?? [])
            ->unique()
            ->values()
            ->each(
                fn ($t) => Process::runWithOutput(
                    Artisan::local("vendor:publish --tag={$t}"),
                ),
            );

        collect($pluginManager->aliasesToRegister())->each(
            fn ($value, $key) => (new ConfigHelper)->update("app.aliases.{$key}", $value)
        );

        collect($pluginManager->providersToRegister())->unique()->values()->each(
            fn ($provider) => (new ConfigHelper)->append('app.providers', $provider),
        );

        $this->step('Update Config Files');

        collect($pluginManager->updateConfig())
            ->merge($config['config'] ?? [])
            ->each(fn ($value, $key) => (new ConfigHelper)->update($key, $value));

        $filesToRename = collect($config['rename-files'] ?? []);

        if ($filesToRename->isNotEmpty()) {
            $this->step('Renaming Files');

            $filesToRename->each(function ($newFile, $oldFile) {
                if (File::missing(Project::path($oldFile))) {
                    $this->warn("File {$oldFile} does not exist, skipping.");

                    return;
                }

                $this->info("{$oldFile} -> {$newFile}");
                File::move(Project::path($oldFile), Project::path($newFile));
            });
        }

        // TODO: Stop littering this config directory everywhere,
        // centralize it and allow it to be overriden for tests
        $toCopy = collect($configFiles)->map(
            fn ($file) => env('HOME') . '/.bellows/kickoff/' . $file
        )->filter(fn ($dir) => is_dir($dir));

        if ($toCopy->isNotEmpty()) {
            $this->step('Copying Files');

            $toCopy->each(function ($src) {
                Process::run("cp -R {$src}/* " . Project::config()->directory);
                $this->info('Copied files from ' . $src);
            });
        }

        $filesToRemove = collect($config['remove-files'] ?? []);

        if ($filesToRemove->isNotEmpty()) {
            $this->step('Removing Files');

            $filesToRemove
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
        }

        $pluginManager->installWrapUp();

        $this->step('Running Migrations');

        Process::runWithOutput(Artisan::local('migrate'));

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
        $configs = collect(glob(env('HOME') . '/.bellows/kickoff/*.json'))
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
