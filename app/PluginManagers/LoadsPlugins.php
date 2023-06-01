<?php

namespace Bellows\PluginManagers;

use Bellows\Config\BellowsConfig;
use Bellows\PluginSdk\Plugin;
use Bellows\StructureDiscoverer\PluginDiscover;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;
use ReflectionClass;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

trait LoadsPlugins
{
    public function getAllAvailablePluginNames(): Collection
    {
        return collect($this->pluginPaths)
            ->flatMap(fn (string $path) => PluginDiscover::in($path)->extending(Scope::raw(Plugin::class))->full()->get())
            ->each(fn (DiscoveredClass $cl) => require $cl->file)
            ->filter(fn (DiscoveredClass $cl) => with(new ReflectionClass($cl->namespace . '\\' . $cl->name))->isInstantiable())
            ->values()
            ->map(fn (DiscoveredClass $cl) => $cl->namespace . '\\' . $cl->name);
    }

    protected function setPluginPaths()
    {
        if (count($this->pluginPaths) === 0) {
            $this->pluginPaths[] = BellowsConfig::getInstance()->path('plugins/vendor');
            require_once BellowsConfig::getInstance()->path('plugins/vendor/autoload.php');
        }
    }

    protected function getAllPlugins(): Collection
    {
        $blacklist = $this->config->get('plugins.launch.blacklist', []);
        $whitelist = $this->config->get('plugins.launch.whitelist', []);

        return $this->getAllAvailablePluginNames()
            ->filter(function (string $plugin) use ($blacklist, $whitelist) {
                if (count($blacklist) > 0) {
                    return !in_array($plugin, $blacklist);
                }

                if (count($whitelist) > 0) {
                    return in_array($plugin, $whitelist);
                }

                return true;
            })
            ->values()
            ->map(fn (string $plugin) => app($plugin))
            ->sortBy([
                fn (Plugin $a, Plugin $b) => $b->priority <=> $a->priority,
                fn (Plugin $a, Plugin $b) => get_class($a) <=> get_class($b),
            ]);
    }
}
