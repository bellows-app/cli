<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\Git\Git;
use Bellows\PluginSdk\Facades\Artisan;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Npm;
use Bellows\PluginSdk\Values\RawValue;
use Closure;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

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

        $installation->manager->gitIgnore($installation->config->get('git-ignore', []))->whenNotEmpty(
            fn ($files) => Git::ignore($files)
        );

        collect($installation->manager->commands($installation->config->get('commands', [])))->map(function ($command) {
            if ($command instanceof RawValue) {
                return (string) $command;
            }

            if (Str::startsWith($command, 'php')) {
                return $command;
            }

            return Artisan::local($command);
        })->each(fn ($command) => Process::runWithOutput($command));

        return $next($installation);
    }
}
