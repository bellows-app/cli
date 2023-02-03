<?php

namespace App\DeployMate;

class Npm extends PackageManager
{
    public function __construct(protected ProjectConfig $config)
    {
    }

    public function packageIsInstalled(string $package): bool
    {
        $npmJson = file_get_contents($this->config->projectDirectory . '/package.json');
        $npmJson = json_decode($npmJson, true);

        return isset($npmJson['dependencies'][$package]);
    }

    public function installPackage(string $package): void
    {
        // $this->info("Installing {$package}...");
        exec("cd {$this->config->projectDirectory} && yarn add {$package}");
    }
}
