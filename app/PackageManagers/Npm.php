<?php

namespace Bellows\PackageManagers;

use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class Npm extends PackageManager
{
    protected $packageManager = null;

    public function getPackageManager(): string
    {
        $this->packageManager ??= $this->detectPackageManager();

        return $this->packageManager;
    }

    public function packageIsInstalled(string $package): bool
    {
        $json = $this->getPackageJson();

        return Arr::get($json, 'dependencies.' . $package) !== null
            || Arr::get($json, 'devDependencies.' . $package) !== null;
    }

    public function install(string|array $package, bool $dev = false): void
    {
        $package = is_array($package) ? implode(' ', $package) : $package;

        if ($this->getPackageManager() === 'yarn') {
            $this->installWithYarn($package, $dev);
        } else {
            $this->installWithNpm($package, $dev);
        }
    }

    public function installDev(string|array $package): void
    {
        $this->install($package, true);
    }

    public function addScriptCommand(string $name, string $command): void
    {
        $json = $this->getPackageJson();

        $json['scripts'][$name] = $command;

        $this->writePackageJson($json);
    }

    public function hasScriptCommand(string $command): bool
    {
        return Arr::get($this->getPackageJson(), 'scripts.' . $command) !== null;
    }

    protected function detectPackageManager(): string
    {
        $lockFile = collect(['yarn.lock', 'package-lock.json'])->first(
            fn ($file) => Project::file($file)->exists(),
        );

        return match ($lockFile) {
            'yarn.lock'         => 'yarn',
            'package-lock.json' => 'npm',
            default             => Console::choice(
                'Which package manager are you using?',
                ['yarn', 'npm'],
                'npm',
            ),
        };
    }

    protected function installWithYarn(string $package, bool $dev = false): void
    {
        if (File::missing(Project::path('yarn.lock'))) {
            File::put(Project::path('yarn.lock'), '');
        }

        $command = 'yarn add ' . $package;

        if ($dev) {
            $command .= ' --dev';
        }

        Process::runWithOutput($command);
    }

    protected function installWithNpm(string $package, bool $dev = false): void
    {
        $command = 'npm install ' . $package;

        if ($dev) {
            $command .= ' --save-dev';
        }

        Process::runWithOutput($command);
    }

    protected function getPackageJson(): array
    {
        $path = Project::path('package.json');

        if (File::missing($path)) {
            return [];
        }

        return File::json($path);
    }

    protected function writePackageJson(array $json): void
    {
        File::put(
            Project::path('package.json'),
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
