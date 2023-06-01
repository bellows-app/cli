<?php

namespace Bellows\StructureDiscoverer\Support;

use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Support\StructuresResolver as BaseStructuresResolver;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class StructuresResolver extends BaseStructuresResolver
{
    /** @return array<DiscoveredStructure> */
    public function discover(
        array $directories,
        array $ignoredFiles = [],
    ): array {
        if (empty($directories)) {
            return [];
        }

        $files = (new Finder())->files()->exclude('vendor')->followLinks()->in($directories);

        $filenames = collect($files)
            ->reject(fn (SplFileInfo $file) => in_array($file->getPathname(), $ignoredFiles) || $file->getExtension() !== 'php')
            ->map(fn (SplFileInfo $file) => $file->getPathname());

        return $this->discoverWorker->run($filenames);
    }
}
