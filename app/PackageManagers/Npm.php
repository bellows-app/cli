<?php

namespace Bellows\PackageManagers;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class Npm extends PackageManager
{
    public static function packageIsInstalled(string $package): bool
    {
        $json = self::getPackageJson();

        return Arr::get($json, 'dependencies.' . $package) !== null
            || Arr::get($json, 'devDependencies.' . $package) !== null;
    }

    public static function installPackage(string $package): void
    {
        Console::info("Installing {$package}...");

        exec(
            sprintf(
                'cd %s && yarn add %s',
                Project::config()->directory,
                $package
            )
        );
    }

    public static function hasScriptCommand(string $command): bool
    {
        return Arr::get(self::getPackageJson(), 'scripts.' . $command) !== null;
    }

    protected function getPackageJson(): array
    {
        $path = Project::config()->directory . '/package.json';

        if (!file_exists($path)) {
            return [];
        }

        $json = File::json($path);

        return $json;
    }
}
