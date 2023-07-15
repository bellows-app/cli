<?php

namespace Bellows\Plugins;

use Bellows\Config;
use Bellows\Config\BellowsConfig;
use Bellows\PluginSdk\Facades\Console;
use Bellows\Util\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class Manager
{
    public function __construct(
        protected Config $config,
    ) {
    }

    public function installDefaults(): void
    {
        if ($this->config->get('plugins.defaults_installed_at')) {
            // We've already installed the default plugins, move along now
            return;
        }

        Console::newLine();
        Console::info('Installing default plugins, one moment...');

        $this->install([
            'bellows-app/plugin-run-schedule',
            'bellows-app/plugin-queue',
            'bellows-app/plugin-optimize',
            'bellows-app/plugin-local-database',
            'bellows-app/plugin-compile-assets',
        ]);

        $this->config->set('plugins.defaults_installed_at', now()->toDateTimeString());
    }

    public function install(string|array $plugins, $showOutput = false)
    {
        $plugins = is_array($plugins) ? $plugins : [$plugins];

        $pluginComposerPath = BellowsConfig::getInstance()->pluginsPath('composer.json');

        if (File::missing($pluginComposerPath)) {
            File::put(
                $pluginComposerPath,
                json_encode(
                    [
                        'name'        => Vendor::namespace() . '/plugins',
                        'description' => 'Bellows plugins',
                        'require'     => (object) [],
                    ],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );
        }

        collect($plugins)->each(function ($plugin) use ($showOutput) {
            $method = $showOutput ? 'runWithOutput' : 'run';

            Process::$method(
                sprintf(
                    'cd %s && composer require %s --no-interaction',
                    BellowsConfig::getInstance()->pluginsPath(''),
                    // TODO: Remove this after we tag
                    $plugin . ':dev-main',
                ),
            );
        });
    }

    public function remove(string|array $plugins, $showOutput = false)
    {
        $plugins = is_array($plugins) ? $plugins : [$plugins];

        collect($plugins)->each(function ($plugin) use ($showOutput) {
            $method = $showOutput ? 'runWithOutput' : 'run';

            Process::$method(
                sprintf(
                    'cd %s && composer remove %s --no-interaction',
                    BellowsConfig::getInstance()->pluginsPath(''),
                    $plugin,
                ),
            );
        });
    }

    public function installed(): Collection
    {
        $pluginComposerPath = BellowsConfig::getInstance()->pluginsPath('composer.json');

        if (File::missing($pluginComposerPath)) {
            return collect();
        }

        $composerJson = File::json($pluginComposerPath);

        return collect($composerJson['require'] ?? [])->keys();
    }

    public function hasAnyInstalled(): bool
    {
        return $this->installed()->isNotEmpty();
    }
}
