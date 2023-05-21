<?php

namespace Bellows\Commands;

use Bellows\Data\PhpVersion;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\PluginManagerInterface;
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
        $this->warn('');
        $this->info("🚀 Let's create a new project! This is exciting.");
        $this->newLine();

        $dir = trim(Process::run('pwd')->output());

        Project::setDir($dir);

        $this->comment("Creating project in {$dir}");

        if (Process::run('ls -A .')->output() !== '') {
            $this->error('Directory is not empty. Kickoff only works with a fresh directory.');
            exit;
        }

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

        $pluginManager->setActiveForInstall();

        $this->step('Installing Laravel');

        // TODO: This is also probably a plugin? Maybe not, it always has to be done.
        Process::run(
            'composer create-project laravel/laravel .',
            function (string $type, string $output) {
                echo $output;
            },
        );

        file_put_contents($dir . '/README.md', "# {$name}");

        $this->step('Environment Variables');

        $updatedEnvValues = collect(array_merge(
            [
                'APP_NAME'     => Project::config()->appName,
                'APP_URL'      => 'http://' . Project::config()->domain,
                'VITE_APP_ENV' => '${APP_ENV}',
            ],
            $pluginManager->environmentVariables(),
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

        [$devPackages, $prodPackages] = collect(
            $pluginManager->composerPackagesToInstall()
        )->partition(fn ($package) => is_array($package));

        if ($devPackages->isNotEmpty()) {
            Composer::require($devPackages->map(fn ($p) => $p[0])->toArray(), true);
        }

        if ($prodPackages->isNotEmpty()) {
            Composer::require($prodPackages->toArray());
        }

        $this->step('NPM Packages');

        [$devPackages, $prodPackages] = collect(
            $pluginManager->npmPackagesToInstall()
        )->partition(fn ($package) => is_array($package));

        if ($devPackages->isNotEmpty()) {
            Npm::install($devPackages->map(fn ($p) => $p[0])->toArray(), true);
        }

        if ($prodPackages->isNotEmpty()) {
            Npm::install($prodPackages->toArray());
        }

        dd('done');

        // TODO: Handle teams here? Probably not?
        // exec('php artisan queue:table');
        Process::run('php artisan migrate');

        $this->commitChanges('composer packages added');

        $this->installJSPackages();

        $this->commitChanges('js packages added');

        $this->handleValet();

        $this->step('Copying files...');

        exec('echo ".yarn" >> .gitignore');

        $this->copyNewFiles();

        $this->removeOldFiles();

        $this->commitChanges('freshly kicked off');

        // Add repo to GitHub Desktop?
        exec('github .');

        $this->info('Consider yourself kicked off. 🚀');

        // TODO: CS command
        $this->info($this->url);
    }

    protected function commitChanges(string $message)
    {
        exec('git add .');
        exec('git commit -m "' . $message . '"');
        exec('git push');
    }

    protected function handleValet()
    {
        $this->step('Linking in Valet...');

        exec("valet link {$this->urlBase}");
        exec("valet secure {$this->urlBase}");
        exec("valet isolate php@8.1 --site=\"{$this->urlBase}\"");
    }

    protected function copyNewFiles()
    {
        $paths = collect();

        $dirIterator = new \RecursiveDirectoryIterator(resource_path('new-project'));
        /** @var \RecursiveDirectoryIterator | \RecursiveIteratorIterator $it */
        $it = new \RecursiveIteratorIterator($dirIterator);

        // the valid() method checks if current position is valid eg there is a valid file or directory at the current position
        while ($it->valid()) {
            // isDot to make sure it is not current or parent directory
            if (!$it->isDot() && $it->isFile() && $it->isReadable() && !Str::contains($it->current(), '.DS_Store')) {
                $file = $it->current();
                $paths->push((string) $file);
            }

            $it->next();
        }

        $paths->each(function ($path) {
            $newPath = $this->dir . Str::replace(resource_path('new-project'), '', $path);

            if (!file_exists($newPath)) {
                $dir = dirname($newPath);

                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                touch($newPath);
            }

            $toReplace = [
                '{{ APP_URL_HOST }}'       => $this->url,
                '{{ APP_NAME }}'           => $this->name,
                '{{ APP_PRODUCTION_URL }}' => Str::replace('.test', '.com', $this->url),
                '{{ TODAYS_DATE }}'        => CarbonImmutable::now()->format('F j, Y'),
            ];

            $newContent = file_get_contents($path);

            $newContent = Str::replace(array_keys($toReplace), array_values($toReplace), $newContent);

            file_put_contents($newPath, $newContent);
        });
    }

    protected function removeOldFiles()
    {
        collect([
            'resources/js/app.js',
            'vite.config.js',
        ])->each(fn ($p) => unlink($this->dir . '/' . $p));
    }

    protected function installComposerPackages()
    {
        $this->step('Installing composer packages...');

        $packages = collect([
            'laravel/cashier',
            'predis/predis',
            'spatie/laravel-data',
            'spatie/laravel-honeypot',
            'spatie/laravel-ray',
            'spatie/laravel-slack-alerts',
            'spatie/laravel-typescript-transformer',
        ]);

        exec('composer require ' . $packages->implode(' '));
    }

    protected function installJSPackages()
    {

        // TODO: These are just packages we need, we don't need to make full on plugins
        // for them, that's silly. Just install them. But how?
        $packages = collect([
            '@bugsnag/plugin-vue',
            '@headlessui/vue',
            '@heroicons/vue',
            '@inertiajs/server',
            '@tailwindcss/forms',
            '@tailwindcss/typography',
            '@vue/tsconfig',
            'flatpickr',
            'tailwindcss',
        ]);

        $devPackages = collect([
            'vite-plugin-watch',
        ]);
    }

    protected function updateEnv()
    {
        collect([
            'APP_NAME'                      => [$this->name],
            'APP_URL'                       => "https://{$this->url}",
            'SUPPORT_EMAIL'                 => 'joe@joe.codes',
            'STRIPE_KEY'                    => 'pk_test_oLS1gyEDw61F0VOh2NgExNFz',
            'STRIPE_SECRET'                 => 'sk_test_Epuk39b3NRVRy8N8Uf0p2DCG',
            'STRIPE_WEBHOOK_SECRET'         => '',
            'QUEUE_CONNECTION'              => 'database',
            'VITE_APP_ENV'                  => ['${APP_ENV}'],
            // 'DEBUGBAR_ENABLED'              => 'false',
            // 'MAILCOACH_API_TOKEN'           => '',
            // 'OH_DEAR_HEALTH_CHECK_SECRET'   => '',
            // 'SLACK_ALERT_WEBHOOK'           => '',
        ])->map(fn ($v, $k) => $this->updateEnvKeyValue($k, is_array($v) ? $v[0] : $v, is_array($v)));
    }
}
