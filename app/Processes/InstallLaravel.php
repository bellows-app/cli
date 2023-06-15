<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;
use Illuminate\Support\Facades\Process;

class InstallLaravel
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('Installing Laravel');

        Process::runWithOutput('composer create-project laravel/laravel .');

        Project::file('README.md')->write('# ' . Project::appName());

        return $next($installation);
    }
}
