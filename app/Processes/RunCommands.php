<?php

namespace Bellows\Processes;

use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Artisan;
use Bellows\PluginSdk\Values\RawValue;
use Closure;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class RunCommands
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        collect($installation->manager->commands(
            $installation->config->get(KickoffConfigKeys::COMMANDS)
        ))
            ->map(function ($command) {
                if ($command instanceof RawValue) {
                    return (string) $command;
                }

                if (Str::startsWith($command, 'php')) {
                    return $command;
                }

                return Artisan::local($command);
            })
            ->each(fn ($command) => Process::runWithOutput($command));

        return $next($installation);
    }
}
