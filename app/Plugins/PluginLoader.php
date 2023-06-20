<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Plugin;
use Bellows\StructureDiscoverer\PluginDiscover;
use Bellows\Util\Scope;
use Closure;
use Illuminate\Support\Collection;
use ReflectionClass;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

class PluginLoader
{
    public static function discoverInDirectories(
        iterable $directories,
        string|array $interface,
        Closure $extraFilter = null
    ): Collection {
        if (!is_array($interface)) {
            $interface = [$interface];
        }

        return collect($directories)
            ->flatMap(
                fn (string $path) => PluginDiscover::in($path)
                    ->extending(Scope::raw(Plugin::class))
                    ->implementing(...$interface)
                    ->full()
                    ->get()
            )
            ->each(fn (DiscoveredClass $cl) => require $cl->file)
            ->filter(fn (DiscoveredClass $cl) => (new ReflectionClass($cl->namespace . '\\' . $cl->name))->isInstantiable())
            ->filter(fn ($plugins) => $extraFilter ? $extraFilter($plugins) : true)
            ->map(fn (DiscoveredClass $cl) => app($cl->namespace . '\\' . $cl->name))
            ->sortBy([
                fn (Plugin $a, Plugin $b) => $b->priority <=> $a->priority,
                fn (Plugin $a, Plugin $b) => get_class($a) <=> get_class($b),
            ])
            ->values();
    }
}
