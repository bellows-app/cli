<?php

namespace Bellows\PackageManagers;

use Bellows\Facades\Console;
use Bellows\Project;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class Npm extends PackageManager
{
    public function __construct(protected Project $project)
    {
    }

    public function packageIsInstalled(string $package): bool
    {
        $json = $this->getPackageJson();

        return Arr::get($json, 'dependencies.' . $package) !== null
            || Arr::get($json, 'devDependencies.' . $package) !== null;
    }

    public function installPackage(string $package): void
    {
        Console::info("Installing {$package}...");
        exec("cd {$this->project->config->directory} && yarn add {$package}");
    }

    public function hasScriptCommand(string $command): bool
    {
        return Arr::get($this->getPackageJson(), 'scripts.' . $command) !== null;
    }

    protected function getPackageJson(): array
    {
        $path = $this->project->config->directory . '/package.json';

        if (!file_exists($path)) {
            return [];
        }

        $json = File::json($path);

        return $json;
    }
}
