<?php

namespace Bellows\PluginManagers\Abilities;

use Bellows\Config\BellowsConfig;
use Bellows\Plugins\PluginLoader;
use Bellows\PluginSdk\Plugin;
use Bellows\StructureDiscoverer\PluginDiscover;
use Bellows\Util\Scope;
use Closure;
use Illuminate\Support\Collection;
use ReflectionClass;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

trait LoadsPlugins
{
    protected function setPluginPaths()
    {
        if (count($this->pluginPaths) === 0) {
            $this->pluginPaths[] = BellowsConfig::getInstance()->path('plugins/vendor');
            require_once BellowsConfig::getInstance()->path('plugins/vendor/autoload.php');
        }
    }

    protected function getAllPlugins(string $interface, Closure $extraFilter = null): Collection
    {
        return PluginLoader::discoverInDirectories($this->pluginPaths, $interface, $extraFilter);
    }
}
