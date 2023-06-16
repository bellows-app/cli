<?php

namespace Bellows\Processes;

use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Composer;
use Bellows\PluginSdk\Facades\Console;
use Closure;
use Illuminate\Support\Str;

class HandleComposer
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('Composer Packages and Scripts');

        // We're doing this separately from the config below so we dont have to merge recursively
        $installation->manager->composerScripts()->whenNotEmpty(
            fn ($scripts) => $scripts->each(fn ($commands, $event) => Composer::addScript($event, $commands)),
        );

        collect($installation->config->get(KickoffConfigKeys::COMPOSER_SCRIPTS))->whenNotEmpty(
            fn ($scripts) => $scripts->each(fn ($commands, $event) => Composer::addScript($event, $commands)),
        );

        $installation->manager->allowedComposerPlugins(
            $installation->config->get(KickoffConfigKeys::COMPOSER_ALLOW_PLUGINS)
        )->whenNotEmpty(
            fn ($packages) => Composer::allowPlugin($packages->toArray()),
        );

        $installation->manager->composerPackages(
            $installation->config->get(KickoffConfigKeys::COMPOSER)
        )->whenNotEmpty(function ($packages) {
            [$withFlags, $noFlags] = $packages->partition(fn ($package) => Str::contains($package, ' --'));

            if ($noFlags->isNotEmpty()) {
                Composer::require($noFlags->toArray());
            }

            $withFlags->each(fn ($package) => Composer::require($package));
        });

        $installation->manager->composerDevPackages(
            $installation->config->get(KickoffConfigKeys::COMPOSER_DEV)
        )->whenNotEmpty(function ($packages) {
            [$withFlags, $noFlags] = $packages->partition(fn ($package) => Str::contains($package, ' --'));

            if ($noFlags->isNotEmpty()) {
                Composer::requireDev($noFlags->toArray());
            }

            $withFlags->each(fn ($package) => Composer::requireDev($package));
        });

        return $next($installation);
    }
}
