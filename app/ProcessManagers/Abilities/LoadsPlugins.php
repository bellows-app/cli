<?php

namespace Bellows\ProcessManagers\Abilities;

use Bellows\Config\BellowsConfig;
use Bellows\Plugins\PluginLoader;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

trait LoadsPlugins
{
    protected function setPluginPaths()
    {
        if (count($this->pluginPaths) === 0) {
            $autoloadPath = BellowsConfig::getInstance()->path('plugins/vendor/autoload.php');

            if (File::exists($autoloadPath)) {
                $this->pluginPaths[] = BellowsConfig::getInstance()->path('plugins/vendor');
                require_once $autoloadPath;
            }
        }
    }

    protected function getAllPlugins(string|array $interface, Closure $extraFilter = null): Collection
    {
        return PluginLoader::discoverInDirectories($this->pluginPaths, $interface, $extraFilter);
    }
}
