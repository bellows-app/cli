<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Config\KickoffConfig;
use Bellows\Git\Git;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;

class PluginInstall extends Command
{
    protected $signature = 'plugin:install {query?}';

    protected $description = 'Search for and install a Bellows plugin';

    public function handle()
    {
        $this->newLine();
        $query = $this->argument('query');

        $toInstall = $this->search($query);

        $pluginComposerPath = BellowsConfig::getInstance()->pluginsPath('composer.json');

        if (File::missing($pluginComposerPath)) {
            File::put(
                $pluginComposerPath,
                json_encode(
                    [
                        'name' => $this->getVendorNamespace() . '/plugins',
                        'description' => 'Bellows plugins',
                        'require' => (object) [],
                    ],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );
        }

        Process::runWithOutput(
            sprintf(
                "cd %s && composer require %s --no-interaction",
                BellowsConfig::getInstance()->pluginsPath(''),
                // TODO: Remove this after we tag
                $toInstall . ':dev-main',
            ),
        );

        $this->newLine();
        $this->info('Plugin installed successfully!');

        $configs = collect(glob(BellowsConfig::getInstance()->kickoffConfigPath('*.json')))
            ->map(fn ($path) => new KickoffConfig($path))
            ->sortBy(fn (KickoffConfig $config) => $config->displayName());

        if ($configs->count() === 0) {
            return;
        }

        if (!$this->confirm('Would you like to add this plugin to any of your kickoff configs?')) {
            return;
        }

        $selected = $this->choice(
            question: 'Select kickoff config(s) to add this plugin to',
            choices: $configs->map(fn (KickoffConfig $c) => $c->displayName())->toArray(),
            multiple: true,
        );

        $files = $configs->filter(fn (KickoffConfig $c) => in_array($c->displayName(), $selected));

        $dev = $this->confirm('Add this plugin as a dev dependency?');

        $files->each(function (KickoffConfig $config) use ($toInstall, $dev) {
            $config->addComposerDependency($toInstall, $dev);
            $config->writeToFile();
        });

        $this->newLine();
        $this->info(sprintf('Plugin added to kickoff %s successfully!', Str::plural('config', $files->count())));
    }

    protected function getVendorNamespace(): ?string
    {
        return collect([
            $_SERVER['COMPOSER_DEFAULT_VENDOR'] ?? null,
            Git::gitHubUser(),
            $_SERVER['USERNAME'] ?? null,
            $_SERVER['USER'] ?? null,
            get_current_user(),
        ])->filter()->first();
    }

    protected function search(?string $query = null): string
    {
        if ($query === null) {
            $query = $this->askRequired('Search for a plugin');
        }

        $response = Http::acceptJson()->withUserAgent('bellows@joe.codes')->get('https://packagist.org/search.json', [
            'q' => $query,
            'type' => 'bellows-plugin',
            'per_page' => 10,
        ]);

        $results = $response->json();

        if ($results['total'] === 0) {
            $this->error('No plugins found for "' . $query . '"');
            return $this->search();
        }

        $this->info(
            sprintf(
                'Found %d %s result for "%s"',
                $results['total'],
                Str::plural('plugin', $results['total']),
                $query
            ),
        );

        $this->newLine();
        $this->warn('Showing first 10 results');

        if ($results['total'] === 1) {
            $singlePlugin = $results['results'][0];

            $this->newLine();

            $this->comment($singlePlugin['name']);
            $this->info($singlePlugin['description']);

            if ($this->confirm('Would you like to install ' . $singlePlugin['name'] . '?', true)) {
                return $singlePlugin['name'];
            }

            return $this->search();
        }

        $choices = collect($results['results'])->pluck('name')->toArray();

        return $this->choice('Which plugin would you like to install?', $choices);
    }
}