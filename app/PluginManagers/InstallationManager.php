<?php

namespace Bellows\PluginManagers;

use Bellows\Config;
use Bellows\Facades\Console;
use Bellows\PluginManagers\Abilities\CallsMethodsOnPlugins;
use Bellows\PluginManagers\Abilities\HasEnvironmentVariables;
use Bellows\PluginManagers\Abilities\LoadsPlugins;
use Bellows\PluginManagers\Abilities\WrapsUp;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\PluginResults\InstallationResult;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

class InstallationManager
{
    use LoadsPlugins, WrapsUp, HasEnvironmentVariables, CallsMethodsOnPlugins;

    /** @var Collection<\Bellows\PluginSdk\PluginResults\InstallationResult> */
    protected Collection $pluginResults;

    protected array $directoriesToCopy = [];

    public function __construct(
        protected Config $config,
        protected array $pluginPaths = [],
    ) {
        $this->setPluginPaths();
    }

    public function setActive(array $pluginsConfig): void
    {
        $plugins = $this->getAllPlugins(
            Scope::raw(Installable::class),
            function (DiscoveredClass $p) use ($pluginsConfig) {
                // TODO: Probably something more sophisticated than this for matching?
                $matches = Str::endsWith($p->namespace . '\\' . $p->name, $pluginsConfig);

                if (!$matches) {
                    return false;
                }

                // Directory is src, go up one level and look for a files directory
                $filesDir = dirname($p->file) . '/../files';

                if (is_dir($filesDir)) {
                    $this->directoriesToCopy[] = $filesDir;
                }

                return true;
            }
        );

        $this->pluginResults = $plugins->map(function (Installable $p) {
            Console::info("Configuring <comment>{$p->getName()}</comment> plugin...");
            Console::newLine();

            $result = $p->install();

            if ($result === null) {
                return null;
            }

            $result->composerPackages($this->getComposerPackagesFromPlugin($result, $p));
            $result->npmPackages($this->getNpmPackagesFromPlugin($result, $p));

            return $result;
        })->filter()->values();
    }

    protected function getComposerPackagesFromPlugin(InstallationResult $result, Installable $plugin): array
    {
        if (count($plugin->requiredComposerPackages)) {
            return $plugin->requiredComposerPackages;
        }

        if (
            count($plugin->anyRequiredComposerPackages)
            && count(
                array_intersect($plugin->anyRequiredComposerPackages, $result->getComposerPackages())
            ) === 0
        ) {
            return [$plugin->anyRequiredComposerPackages[0]];
        }

        return [];
    }

    protected function getNpmPackagesFromPlugin(InstallationResult $result, Installable $plugin): array
    {
        if (count($plugin->requiredNpmPackages)) {
            return $plugin->requiredNpmPackages;
        }

        if (
            count($plugin->anyRequiredNpmPackages)
            && count(
                array_intersect($plugin->anyRequiredNpmPackages, $result->getNpmPackages())
            ) === 0
        ) {
            return [$plugin->anyRequiredNpmPackages[0]];
        }

        return [];
    }

    public function aliasesToRegister(array $initialValue = []): array
    {
        return $this->call('getAliases')->reduce($initialValue);
    }

    public function directoriesToCopy(): array
    {
        return $this->directoriesToCopy;
    }

    public function serviceProvidersToRegister(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getServiceProviders')->reduce($initialValue)
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

    public function commands(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getCommands')->reduce($initialValue)
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
