<?php

namespace App\DeployMate\PackageManagers;

use App\DeployMate\Data\ProjectConfig;

class Composer extends PackageManager
{
    public function __construct(protected ProjectConfig $config, protected Console $console)
    {
    }

    public function packageIsInstalled(string $package): bool
    {
        $composerJson = file_get_contents($this->config->projectDirectory . '/composer.json');
        $composerJson = json_decode($composerJson, true);

        return isset($composerJson['require'][$package]);
    }

    public function require(string $package, bool $dev): void
    {
        $this->console->info("Installing {$package}...");
        exec("cd {$this->config->projectDirectory} && composer require {$package}");
    }
}
