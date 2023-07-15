<?php

namespace Bellows\Plugins;

use Bellows\Config;
use Bellows\Config\BellowsConfig;
use Bellows\Util\Vendor;
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
                        'name' => Vendor::namespace() . '/plugins',
                        'description' => 'Bellows plugins',
                        'require' => (object) [],
                    ],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );
        }

        collect($plugins)->each(function ($plugin) use ($showOutput) {
            $method = $showOutput ? 'runWithOutput' : 'run';

            Process::$method(
                sprintf(
                    "cd %s && composer require %s --no-interaction",
                    BellowsConfig::getInstance()->pluginsPath(''),
                    // TODO: Remove this after we tag
                    $plugin . ':dev-main',
                ),
            );
        });
    }
}
