<?php

namespace Bellows\PackageManagers;

use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class Composer extends PackageManager
{
    public function packageIsInstalled(string $package): bool
    {
        $composerJson = $this->getComposerJson();

        return isset($composerJson['require'][$package]);
    }

    public function require(string|array $package, bool $dev = false, string $additionalFlags = null): void
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

        $command = "composer require {$package} {$flag}";

        Console::comment($command);

        Process::runWithOutput($command);
    }

    public function requireDev(string|array $package, string $additionalFlags = null): void
    {
        $this->require($package, true, $additionalFlags);
    }

    public function addScript(string $event, string|array $commands): void
    {
        if (!is_array($commands)) {
            $commands = [$commands];
        }

        $composerJson = $this->getComposerJson();

        $currentValue = $composerJson['scripts'][$event] ?? [];

        if (is_string($currentValue)) {
            $currentValue = [$currentValue];
        }

        foreach ($commands as $command) {
            if (!in_array($command, $currentValue)) {
                $currentValue[] = $command;
            }
        }

        $composerJson['scripts'][$event] = $currentValue;

        $this->writeComposerJson($composerJson);
    }

    public function allowPlugin(string|array $plugins): void
    {
        if (!is_array($plugins)) {
            $plugins = [$plugins];
        }

        $plugins = collect($plugins)->mapWithKeys(function ($plugin) {
            return [$plugin => true];
        });

        $composerJson = $this->getComposerJson();

        $composerJson['config']['allow-plugins'] = array_merge(
            $composerJson['config']['allow-plugins'] ?? [],
            $plugins->toArray(),
        );

        $this->writeComposerJson($composerJson);
    }

    protected function getComposerJson(): array
    {
        $path = Project::path('composer.json');

        if (File::missing($path)) {
            return [];
        }

        return File::json($path);
    }

    protected function writeComposerJson(array $composerJson): void
    {
        File::put(
            Project::path('composer.json'),
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
