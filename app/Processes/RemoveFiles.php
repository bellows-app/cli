<?php

namespace Bellows\Processes;

use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;
use Illuminate\Support\Facades\File;

class RemoveFiles
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $toRemove = collect($installation->config->get(KickoffConfigKeys::REMOVE_FILES));

        if ($toRemove->isEmpty()) {
            return $next($installation);
        }

        Console::step('Removing Files');

        $toRemove
            ->map(fn ($file) => Project::dir() . '/' . $file)
            ->filter(function ($file) {
                if (File::exists($file)) {
                    return true;
                }

                Console::warn("File {$file} does not exist, skipping.");

                return false;
            })
            ->each(function ($file) {
                Console::info($file);
                File::delete($file);
            });

        return $next($installation);
    }
}
