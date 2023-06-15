<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;
use Illuminate\Support\Facades\File;

class RenameFiles
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $files = collect($installation->config->get('rename-files', []));

        if ($files->isEmpty()) {
            return $next($installation);
        }

        Console::step('Renaming Files');

        $files->each(function ($newFile, $oldFile) {
            if (!Project::file($oldFile)->exists()) {
                Console::warn("File {$oldFile} does not exist, skipping.");

                return;
            }

            Console::info("{$oldFile} -> {$newFile}");
            File::move(Project::path($oldFile), Project::path($newFile));
        });

        return $next($installation);
    }
}
