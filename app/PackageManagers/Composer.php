<?php

namespace Bellows\PackageManagers;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Illuminate\Support\Facades\File;

class Composer extends PackageManager
{
    public static function packageIsInstalled(string $package): bool
    {
        $composerJson = static::getComposerJson();

        return isset($composerJson['require'][$package]);
    }

    public static function require(string $package, bool $dev): void
    {
        Console::info("Installing {$package}...");

        exec(
            sprintf(
                'cd %s && composer require %s',
                Project::config()->directory,
                $package
            )
        );
    }

    protected static function getComposerJson(): array
    {
        $path = Project::config()->directory . '/composer.json';

        if (!file_exists($path)) {
            return [];
        }

        return File::json($path);
    }
}
