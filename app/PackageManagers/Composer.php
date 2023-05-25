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

    public static function require(string|array $package, bool $dev = false, string $additionalFlags = null): void
    {
        $package = is_array($package) ? implode(' ', $package) : $package;

        $flags = collect([
            '--no-interaction',
        ]);

        if ($dev) {
            $flags->push('--dev');
        }

        if ($additionalFlags) {
            $flags->push($additionalFlags);
        }

        $flag = $flags->implode(' ');

        Process::runWithOutput("composer require {$package} {$flag}");
    }

    public static function addScript(string $key, string $value): void
    {
        $composerJson = static::getComposerJson();

        $currentValue = $composerJson['scripts'][$key] ?? [];

        array_push($currentValue, $value);

        $composerJson['scripts'][$key] = $currentValue;

        static::writeComposerJson($composerJson);
    }

    public static function allowPlugin(string $plugin): void
    {
        $composerJson = static::getComposerJson();

        $composerJson['config']['allow-plugins'] = array_merge(
            $composerJson['config']['allow-plugins'] ?? [],
            [$plugin => true],
        );

        static::writeComposerJson($composerJson);
    }

    protected static function getComposerJson(): array
    {
        $path = Project::path('composer.json');

        if (!file_exists($path)) {
            return [];
        }

        return File::json($path);
    }

    protected static function writeComposerJson(array $composerJson): void
    {
        File::put(
            Project::path('composer.json'),
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
