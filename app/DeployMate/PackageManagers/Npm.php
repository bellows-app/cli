<?php

namespace App\DeployMate\PackageManagers;

use App\DeployMate\Console;
use App\DeployMate\Data\ProjectConfig;
use Illuminate\Support\Arr;

class Npm extends PackageManager
{
    public function __construct(protected ProjectConfig $config, protected Console $console)
    {
    }

    public function packageIsInstalled(string $package): bool
    {
        $npmJson = file_get_contents($this->config->projectDirectory . '/package.json');
        $npmJson = json_decode($npmJson, true);

        return Arr::get($npmJson, 'dependencies.' . $package) || Arr::get($npmJson, 'devDependencies.' . $package);
    }

    public function installPackage(string $package): void
    {
        $this->console->info("Installing {$package}...");
        exec("cd {$this->config->projectDirectory} && yarn add {$package}");
    }
}
