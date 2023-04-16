<?php

namespace Bellows\PackageManagers;

use Bellows\Console;
use Bellows\Data\ProjectConfig;
use Illuminate\Support\Facades\File;

class Composer extends PackageManager
{
    public function __construct(protected ProjectConfig $config, protected Console $console)
    {
    }

    public function packageIsInstalled(string $package): bool
    {
        $composerJson = $this->getComposerJson();

        return isset($composerJson['require'][$package]);
    }

    public function require(string $package, bool $dev): void
    {
        $this->console->info("Installing {$package}...");
        exec("cd {$this->config->projectDirectory} && composer require {$package}");
    }

    protected function getComposerJson(): array
    {
        $path = $this->config->projectDirectory . '/composer.json';

        if (!file_exists($path)) {
            return [];
        }

        return File::json($path);
    }
}
