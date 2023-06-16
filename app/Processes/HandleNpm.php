<?php

namespace Bellows\Processes;

use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Npm;
use Closure;

class HandleNpm
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('NPM Packages');

        $installation->manager->npmPackages(
            $installation->config->get(KickoffConfigKeys::NPM)
        )->whenNotEmpty(fn ($packages) => Npm::install($packages->toArray()));

        $installation->manager->npmDevPackages(
            $installation->config->get(KickoffConfigKeys::NPM_DEV)
        )->whenNotEmpty(fn ($packages) => Npm::installDev($packages->toArray()));

        return $next($installation);
    }
}
