<?php

namespace Bellows\PackageManagers;

abstract class PackageManager
{
    public static function getName()
    {
        return collect(explode('\\', static::class))->last();
    }

    public static function allPackagesAreInstalled(array $packages): bool
    {
        return collect($packages)->filter(
            fn ($package) => static::packageIsInstalled($package)
        )->count() === count($packages);
    }

    public static function anyPackagesAreInstalled(array $packages): bool
    {
        return collect($packages)->first(
            fn ($package) => static::packageIsInstalled($package)
        ) !== null;
    }

    abstract public static function packageIsInstalled(string $package): bool;
}
