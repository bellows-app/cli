<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Npm;
use Closure;

class HandleNpm
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('NPM Packages');

        $installation->manager->npmPackages($installation->config->get('npm', []))->whenNotEmpty(
            fn ($packages) => Npm::install($packages->toArray())
        );

        $installation->manager->npmDevPackages($installation->config->get('npm-dev', []))->whenNotEmpty(
            fn ($packages) => Npm::install($packages->toArray(), true),
        );

        return $next($installation);
    }
}
