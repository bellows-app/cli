<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Config\KickoffConfig;
use Bellows\Config\KickoffConfigKeys;
use Bellows\Git\Git;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;

class PluginRemove extends Command
{
    protected $signature = 'plugin:remove {query?}';

    protected $description = 'Remove a Bellows plugin';

    public function handle()
    {
        $this->newLine();

        $pluginComposerPath = BellowsConfig::getInstance()->pluginsPath('composer.json');

        if (File::missing($pluginComposerPath)) {
            $this->error('No plugins installed! Nothing to remove.');
            return;
        }

        $toRemove = $this->search($this->argument('query'));

        Process::runWithOutput(
            sprintf(
                "cd %s && composer remove %s --no-interaction",
                BellowsConfig::getInstance()->pluginsPath(''),
                $toRemove,
            ),
        );

        $this->newLine();
        $this->info('Plugin removed!');

        $configs = collect(glob(BellowsConfig::getInstance()->kickoffConfigPath('*.json')))
            ->map(fn ($path) => new KickoffConfig($path))
            ->sortBy(fn (KickoffConfig $config) => $config->displayName());

        if ($configs->count() === 0) {
            return;
        }

        $configsWithPlugin = $configs->filter(
            fn (KickoffConfig $config) => collect($config->get(KickoffConfigKeys::PLUGINS))->contains($toRemove)
        );

        if ($configsWithPlugin->isEmpty()) {
            return;
        }

        $this->newLine();

        $this->info(
            sprintf(
                'This plugin is currently used in the following kickoff %s:',
                Str::plural('config', $configsWithPlugin->count()),
            ),
        );

        $this->newLine();

        $configsWithPlugin->each(function (KickoffConfig $config) {
            $this->info('- ' . $config->displayName());
        });

        if (!$this->confirm('Remove plugin from configs?', true)) {
            return;
        }

        $configsWithPlugin->each(function (KickoffConfig $config) use ($toRemove) {
            $config->removePlugin($toRemove);
            $config->writeToFile();
        });

        $this->newLine();
        $this->info(sprintf('Plugin removed from kickoff %s.', Str::plural('config', $configsWithPlugin->count())));
    }

    protected function search(?string $query = null): string
    {
        $composerJson = File::json(BellowsConfig::getInstance()->pluginsPath('composer.json'));
        $current = collect($composerJson['require'] ?? [])->keys();

        if ($query !== null) {
            $filtered = $current->filter(
                fn ($package) => Str::contains($package, strtolower($query))
            )->values();

            $current = $filtered->isEmpty() ? $current : $filtered;
        }

        if ($current->count() === 1) {
            if ($this->confirm('Would you like to remove <comment>' . $current->first() . '</comment>?', true)) {
                return $current->first();
            }

            return $this->search();
        }

        return $this->choice('Which plugin would you like to remove', $current->toArray());
    }
}
