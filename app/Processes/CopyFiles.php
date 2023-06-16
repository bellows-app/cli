<?php

namespace Bellows\Processes;

use Bellows\Config\BellowsConfig;
use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;
use Illuminate\Support\Facades\File;

class CopyFiles
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $directoriesFromKickoffConfig = collect($installation->config->all())
            ->map(fn ($name) => BellowsConfig::getInstance()->path('kickoff/files/' . $name))
            ->filter(fn ($dir) => is_dir($dir));

        $installation->config->merge(
            KickoffConfigKeys::DIRECTORIES_TO_COPY,
            $directoriesFromKickoffConfig->toArray()
        );

        $toCopy = $installation->manager->directoriesToCopy(
            $installation->config->get(KickoffConfigKeys::DIRECTORIES_TO_COPY)
        );

        if ($toCopy->isEmpty()) {
            return $next($installation);
        }

        Console::step('Copying Files');

        $toCopy->each(function ($src) {
            File::copyDirectory($src, Project::dir());
            Console::info('Copied files from ' . $src);
        });

        return $next($installation);
    }
}
