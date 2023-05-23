<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Support\Facades\Process;

class Valet extends Plugin implements Installable
{
    use CanBeInstalled;

    public function installWrapUp(): void
    {
        $urlBase = collect(explode('.', Project::config()->domain))->slice(0, -1)->implode('.');

        ray($urlBase, Project::config()->domain);

        if (Console::confirm('Link this directory in Valet?', true)) {
            Process::run('valet link ' . $urlBase, function ($type, $line) {
                echo $line;
            });
        }

        if (Console::confirm('Secure this domain with Valet?', true)) {
            Process::run('valet secure ' . $urlBase, function ($type, $line) {
                echo $line;
            });
        }

        if (Console::confirm('Isolate PHP version for this project?', true)) {
            $phpVersionsInstalled = Process::run('ls /opt/homebrew/Cellar | grep php@')->output();

            $phpVersionsInstalled = collect(explode("\n", $phpVersionsInstalled))
                ->filter(fn ($version) => $version !== '')
                ->values();

            $phpVersion = Console::choice(
                'Which PHP version?',
                $phpVersionsInstalled->toArray(),
                $phpVersionsInstalled->last()
            );

            Process::run(
                sprintf('valet isolate %s --site="%s"', $phpVersion, $urlBase),
                function ($type, $line) {
                    echo $line;
                }
            );
        }
    }
}
