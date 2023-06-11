<?php

namespace Bellows\PackageManagers;

use Bellows\Facades\Project;
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

        Process::runWithOutput("composer require {$package} {$flag}");
    }

    public function requireDev(string|array $package, string $additionalFlags = null): void
    {
        $this->require($package, true, $additionalFlags);
    }

    public function addScript(string $key, string $value): void
    {
        $composerJson = $this->getComposerJson();

        $currentValue = $composerJson['scripts'][$key] ?? [];

        array_push($currentValue, $value);

        $composerJson['scripts'][$key] = $currentValue;

        $this->writeComposerJson($composerJson);
    }

    public function allowPlugin(string $plugin): void
    {
        $composerJson = $this->getComposerJson();

        $composerJson['config']['allow-plugins'] = array_merge(
            $composerJson['config']['allow-plugins'] ?? [],
            [$plugin => true],
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
