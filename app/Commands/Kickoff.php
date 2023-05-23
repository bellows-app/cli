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

        [$configFile, $config] = $this->getConfig();

        // TODO: Also check for a "default" or "common" config for all projects?
        // Or is it better to have an "extend" property? Or both?

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

        // TODO: This is also probably a plugin? Maybe not, it always has to be done.
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

        $this->step('Update Config Files');

        collect($pluginManager->updateConfig())
            ->merge($config['config'] ?? [])
            ->each(fn ($value, $key) => (new ConfigHelper)->update($key, $value));

        // TODO: Stop littering this config directory everywhere,
        // centralize it and allow it to be overriden for tests
        $copySourceDirectory = env('HOME') . '/.bellows/kickoff/' . $configFile;

        if (is_dir($copySourceDirectory)) {
            $this->step('Copying Files');

            Process::run("cp -R {$copySourceDirectory}/* " . Project::config()->directory);

            $this->info('Copied files from ' . $copySourceDirectory);
        }

        $filesToRemove = collect($config['remove-files'] ?? [])
            ->map(fn ($file) => Project::config()->directory . '/' . $file)
            ->filter(fn ($file) => File::exists($file));

        if ($filesToRemove->isNotEmpty()) {
            $this->step('Removing Files');

            $filesToRemove->each(function ($file) {
                $this->info($file);
                File::delete($file);
            });
        }

        $pluginManager->installWrapUp();

        $this->step('Running Migrations');

        Process::runWithOutput(Artisan::local('migrate'));

        $this->step('Consider yourself kicked off!');

        $this->info('Your new project awaits: <comment>' . Project::env()->get('APP_URL') . '</comment>');

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
            ->sortBy('name');

        // TODO: Offer to init a config if none exist
        if ($configs->count() === 1) {
            $config = $configs->first();

            return [$config['file'], $config['config']];
        }

        $name = $this->choice(
            'What kind of project are we kicking off today?',
            $configs->pluck('name')->toArray(),
        );

        $config = $configs->firstWhere('name', $name);

        return [$config['file'], $config['config']];
    }

    protected function updateEnv()
    {
        // TODO: Plugins: Stripe, Debugbar, Mailcoach, Oh Dear, Slack Webhook
        collect([
            // 'SUPPORT_EMAIL'                 => 'joe@joe.codes',
            // 'STRIPE_KEY'                    => 'pk_test_oLS1gyEDw61F0VOh2NgExNFz',
            // 'STRIPE_SECRET'                 => 'sk_test_Epuk39b3NRVRy8N8Uf0p2DCG',
            // 'STRIPE_WEBHOOK_SECRET'         => '',
            // 'QUEUE_CONNECTION'              => 'database',
            // 'DEBUGBAR_ENABLED'              => 'false',
            // 'MAILCOACH_API_TOKEN'           => '',
            // 'OH_DEAR_HEALTH_CHECK_SECRET'   => '',
            // 'SLACK_ALERT_WEBHOOK'           => '',
        ])->map(fn ($v, $k) => $this->updateEnvKeyValue($k, is_array($v) ? $v[0] : $v, is_array($v)));
    }
}
