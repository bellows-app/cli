<?php

namespace Bellows\Processes;

use Bellows\Config\BellowsConfig;
use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\Util\ConfigHelper;
use Closure;
use Illuminate\Support\Str;

class UpdateConfigFiles
{
    public function __construct(protected readonly ConfigHelper $configHelper)
    {
    }

    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('Updating Config Files');

        // TODO: This is repeated logic from elsewhere but it's fine for now to reduce further bugs.
        // But let's centralize this perhaps.
        $providersFromKickoffFiles = collect($installation->config->all())
            ->map(fn ($name) => BellowsConfig::getInstance()->path('kickoff/files/' . $name))
            ->filter(fn ($dir) => is_dir($dir))
            ->map(fn ($dir) => glob("{$dir}/app/Providers/*.php"))
            ->flatten()
            ->filter()
            ->map(fn ($file) => basename($file, '.php'))
            ->map(fn ($filename) => 'App\\Providers\\' . $filename);

        $aliasesFromConfig = array_merge(
            $installation->config->get(KickoffConfigKeys::FACADES),
            $installation->config->get(KickoffConfigKeys::ALIASES),
        );

        collect($installation->manager->aliasesToRegister($aliasesFromConfig))->each(
            fn ($value, $key) => $this->configHelper->update(
                "app.aliases.{$key}",
                Str::finish($value, '::class'),
            )
        );

        collect($installation->manager->serviceProvidersToRegister(
            array_merge(
                $installation->config->get(KickoffConfigKeys::SERVICE_PROVIDERS),
                $providersFromKickoffFiles->toArray(),
            ),
        ))->each(fn ($provider) => $this->configHelper->append(
            'app.providers',
            Str::finish($provider, '::class'),
        ));

        collect($installation->manager->updateConfig(
            $installation->config->get(KickoffConfigKeys::CONFIG)
        ))->each(fn ($value, $key) => $this->configHelper->update($key, $value));

        return $next($installation);
    }
}
