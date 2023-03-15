<?php

namespace Bellows\PackageManagers;

abstract class PackageManager
{
    abstract public function packageIsInstalled(string $package): bool;

    public function allPackagesAreInstalled(array $packages): bool
    {
        return collect($packages)->filter(
            fn ($package) => $this->packageIsInstalled($package)
        )->count() === count($packages);
    }

    public function anyPackagesAreInstalled(array $packages): bool
    {
        return collect($packages)->first(
            fn ($package) => $this->packageIsInstalled($package)
        ) !== null;
    }
}
