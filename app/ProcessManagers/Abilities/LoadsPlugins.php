<?php

namespace Bellows\ProcessManagers\Abilities;

use Bellows\Config\BellowsConfig;
use Bellows\Plugins\PluginLoader;
use Closure;
use Illuminate\Support\Collection;

trait LoadsPlugins
{
    protected function setPluginPaths()
    {
        if (count($this->pluginPaths) === 0) {
            $this->pluginPaths[] = BellowsConfig::getInstance()->path('plugins/vendor');
            require_once BellowsConfig::getInstance()->path('plugins/vendor/autoload.php');
        }
    }

    protected function getAllPlugins(string|array $interface, Closure $extraFilter = null): Collection
    {
        return PluginLoader::discoverInDirectories($this->pluginPaths, $interface, $extraFilter);
    }
}
