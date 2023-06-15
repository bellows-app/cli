<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;
use Illuminate\Support\Facades\File;

class CopyFiles
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $toCopy = $installation->manager->directoriesToCopy($installation->config->get('directories-to-copy', []));

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
