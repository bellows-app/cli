<?php

namespace Bellows\PackageManagers;

use Bellows\Facades\Project;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class Npm extends PackageManager
{
    public static function getPackageManager(): ?string
    {
        $lockFile = collect(['yarn.lock', 'package-lock.json'])->first(
            fn ($file) => file_exists(Project::config()->directory . '/' . $file)
        );

        return match ($lockFile) {
            'yarn.lock'         => 'yarn',
            'package-lock.json' => 'npm',
            default             => null,
        };
    }

    public static function packageIsInstalled(string $package): bool
    {
        $json = static::getPackageJson();

        return Arr::get($json, 'dependencies.' . $package) !== null
            || Arr::get($json, 'devDependencies.' . $package) !== null;
    }

    public static function install(string|array $package, bool $dev = false): void
    {
        $package = is_array($package) ? implode(' ', $package) : $package;

        if (!file_exists(Project::config()->directory . '/yarn.lock')) {
            touch(Project::config()->directory . '/yarn.lock');
        }

        // TODO: Deal with yarn vs npm
        $flag = $dev ? ' -D' : '';

        Process::run("yarn add{$flag} {$package}", function (string $type, string $output) {
            echo $output;
        });
    }

    public static function hasScriptCommand(string $command): bool
    {
        return Arr::get(static::getPackageJson(), 'scripts.' . $command) !== null;
    }

    protected static function getPackageJson(): array
    {
        $path = Project::config()->directory . '/package.json';

        if (!file_exists($path)) {
            return [];
        }

        $json = File::json($path);

        return $json;
    }
}
