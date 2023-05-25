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

    protected bool $secure = false;

    public function install(): void
    {
        $urlBase = collect(explode('.', Project::config()->domain))->slice(0, -1)->implode('.');

        if (Console::confirm('Link this directory in Valet?', true)) {
            Process::runWithOutput('valet link ' . $urlBase);
        }

        if (Console::confirm('Secure this domain with Valet?', true)) {
            Process::runWithOutput('valet secure ' . $urlBase);

            $this->secure = true;
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

            Process::runWithOutput(sprintf('valet isolate %s --site="%s"', $phpVersion, $urlBase));
        }
    }

    public function environmentVariables(): array
    {
        if ($this->secure) {
            return [
                'APP_URL' => 'https://' . Project::config()->domain,
            ];
        }

        return [];
    }
}
