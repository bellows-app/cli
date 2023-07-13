<?php

namespace Bellows\Processes;

use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Artisan;
use Bellows\PluginSdk\Facades\Value;
use Bellows\PluginSdk\Values\RawValue;
use Closure;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class WrapUpInstallation
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $installation->manager->wrapUp();

        $fromConfig = collect($installation->config->get(KickoffConfigKeys::WRAP_UP_COMMANDS))->map(
            fn ($command) => Value::raw($command)
        )->toArray();

        // TODO: wrapUpCommands in SDK/InstallationManager?
        collect($fromConfig)->map(function ($command) {
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
