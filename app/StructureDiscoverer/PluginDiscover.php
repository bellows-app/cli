<?php

namespace Bellows\StructureDiscoverer;

use Bellows\StructureDiscoverer\Support\StructuresResolver;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\Support\LaravelDetector;

class PluginDiscover extends Discover
{
    public static function in(string ...$directories): self
    {
        if (LaravelDetector::isRunningLaravel()) {
            return app(self::class, [
                'directories' => $directories,
            ]);
        }

        return new self(
            directories: $directories,
        );
    }

    /** @return array<DiscoveredStructure>|array<string> */
    public function getWithoutCache(): array
    {
        $discoverer = new StructuresResolver($this->config->worker);

        return $discoverer->execute($this);
    }
}
