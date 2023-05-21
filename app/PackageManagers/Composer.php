<?php

namespace Bellows\PackageManagers;

use Bellows\Facades\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class Composer extends PackageManager
{
    public static function packageIsInstalled(string $package): bool
    {
        $composerJson = static::getComposerJson();

        return isset($composerJson['require'][$package]);
    }

    public static function require(string|array $package, bool $dev = false): void
    {
        $package = is_array($package) ? implode(' ', $package) : $package;

        $flag = $dev ? '--dev' : '';

        Process::run("composer require {$package} {$flag}", function (string $type, string $output) {
            echo $output;
        });
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
