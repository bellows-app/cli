<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\Util\ConfigHelper;
use Closure;
use Illuminate\Support\Str;

class UpdateConfigFiles
{
    public function __construct(protected readonly ConfigHelper $helper)
    {
    }

    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('Update Config Files');

        $aliasesFromConfig = array_merge(
            $installation->config->get('facades', []),
            $installation->config->get('aliases', []),
        );

        collect($installation->manager->aliasesToRegister($aliasesFromConfig))->each(
            fn ($value, $key) => (new ConfigHelper)->update(
                "app.aliases.{$key}",
                Str::finish($value, '::class'),
            )
        );

        collect($installation->manager->serviceProvidersToRegister($installation->config->get('service-providers', [])))->each(
            fn ($provider) => (new ConfigHelper)->append(
                'app.providers',
                Str::finish($provider, '::class'),
            ),
        );

        collect($installation->manager->updateConfig($installation->config->get('config', [])))->each(
            fn ($value, $key) => (new ConfigHelper)->update($key, $value)
        );

        return $next($installation);
    }
}
