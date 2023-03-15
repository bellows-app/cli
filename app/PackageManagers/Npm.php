<?php

namespace Bellows\PackageManagers;

use Bellows\Console;
use Bellows\Data\ProjectConfig;
use Illuminate\Support\Arr;

class Npm extends PackageManager
{
    public function __construct(protected ProjectConfig $config, protected Console $console)
    {
    }

    public function packageIsInstalled(string $package): bool
    {
        $json = $this->getPackageJson();

        return Arr::get($json, 'dependencies.' . $package) || Arr::get($json, 'devDependencies.' . $package);
    }

    public function installPackage(string $package): void
    {
        $this->console->info("Installing {$package}...");
        exec("cd {$this->config->projectDirectory} && yarn add {$package}");
    }

    public function hasScriptCommand(string $command): bool
    {
        return Arr::get($this->getPackageJson(), 'scripts.' . $command);
    }

    protected function getPackageJson(): array
    {
        $file = file_get_contents($this->config->projectDirectory . '/package.json');
        $json = json_decode($file, true);

        return $json;
    }
}
