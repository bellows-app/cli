<?php

namespace Bellows\PackageManagers;

use Bellows\Facades\Console;
use Bellows\Project;
use Illuminate\Support\Facades\File;

class Composer extends PackageManager
{
    public function __construct(protected Project $project)
    {
    }

    public function packageIsInstalled(string $package): bool
    {
        $composerJson = $this->getComposerJson();

        return isset($composerJson['require'][$package]);
    }

    public function require(string $package, bool $dev): void
    {
        Console::info("Installing {$package}...");
        exec("cd {$this->project->config->directory} && composer require {$package}");
    }

    protected function getComposerJson(): array
    {
        $path = $this->project->config->directory . '/composer.json';

        if (!file_exists($path)) {
            return [];
        }

        return File::json($path);
    }
}
