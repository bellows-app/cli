<?php

namespace Bellows\PluginManagers;

use Bellows\Config;
use Bellows\Facades\Console;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Plugin;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;

class InstallationManager
{
    use LoadsPlugins, WrapsUp, HasEnvironmentVariables, CallsMethodsOnPlugins;

    /** @var Collection<\Bellows\PluginSdk\PluginResults\InstallationResult> */
    protected Collection $pluginResults;

    public function __construct(
        protected Config $config,
        protected array $pluginPaths = [],
    ) {
        $this->setPluginPaths();
    }

    public function setActive(array $pluginsConfig): void
    {
        $plugins = $this->getAllPlugins()->filter(
            fn (Plugin $plugin) => (new ReflectionClass($plugin))->implementsInterface(Scope::raw(Installable::class))
        )->filter(
            // TODO: Probably something more sophisticated than this for matching?
            fn ($p) => Str::endsWith(get_class($p), $pluginsConfig)
        )->values();

        $this->pluginResults = $plugins->map(function (Installable $p) {
            Console::info("Configuring <comment>{$p->getName()}</comment> plugin...");
            Console::newLine();

            return $p->install();
        })->filter()->values();
    }

    public function aliasesToRegister(): array
    {
        return $this->call('getAliases')->reduce([]);
    }

    public function serviceProvidersToRegister(): Collection
    {
        return $this->uniqueCollection(
            $this->call('getServiceProviders')->reduce([])
        );
    }

    public function publishTags(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getPublishTags')->reduce($initialValue)
        );
    }

    public function updateConfig(array $initialValue = []): array
    {
        return $this->call('getUpdateConfig')->reduce($initialValue);
    }

    public function commands(): Collection
    {
        return $this->uniqueCollection(
            $this->call('getCommands')->reduce([])
        );
    }

    public function composerPackages(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getComposerPackages')->reduce($initialValue)
        );
    }

    public function composerDevPackages(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getComposerDevPackages')->reduce($initialValue)
        );
    }

    public function npmPackages(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getNpmPackages')->reduce($initialValue)
        );
    }

    public function npmDevPackages(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getNpmDevPackages')->reduce($initialValue)
        );
    }

    protected function uniqueCollection(array $arr): Collection
    {
        return collect($arr)->unique()->values();
    }
}
