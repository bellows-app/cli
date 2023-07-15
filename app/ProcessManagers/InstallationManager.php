<?php

namespace Bellows\ProcessManagers;

use Bellows\Config;
use Bellows\ProcessManagers\Abilities\CallsMethodsOnPlugins;
use Bellows\ProcessManagers\Abilities\HasEnvironmentVariables;
use Bellows\ProcessManagers\Abilities\LoadsPlugins;
use Bellows\ProcessManagers\Abilities\WrapsUp;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\PluginResults\InstallationResult;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
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

    public function setActive(array $pluginPackageNames): void
    {
        $plugins = $this->getAllPlugins(
            Scope::raw(Installable::class),
            function (DiscoveredClass $p) use ($pluginPackageNames) {
                // Directory is src, go up one level and look for a files directory
                $pluginBaseDir = File::dirname($p->file) . '/..';

                $composerFile = "{$pluginBaseDir}/composer.json";

                if (!File::exists($composerFile)) {
                    return false;
                }

                $composer = File::json($composerFile);

                if (!in_array($composer['name'] ?? '', $pluginPackageNames)) {
                    return false;
                }

                $filesDir = "{$pluginBaseDir}/files";

                if (File::isDirectory($filesDir)) {
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

    public function aliasesToRegister(array $initialValue = []): array
    {
        return $this->call('getAliases')->reduce($initialValue);
    }

    public function directoriesToCopy(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getDirectoriesToCopy')->reduce(array_merge($this->directoriesToCopy, $initialValue))
        );
    }

    public function serviceProvidersToRegister(array $initialValue = []): Collection
    {
        // Auto-register providers from the app/Providers directory of any directories we're copying
        $providersFromDirectories = $this->directoriesToCopy()
            ->map(fn ($dir) => glob("{$dir}/app/Providers/*.php"))
            ->flatten()
            ->filter()
            ->map(fn ($file) => basename($file, '.php'))
            ->map(fn ($filename) => 'App\\Providers\\' . $filename);

        return $this->uniqueCollection(
            $this->call('getServiceProviders')->reduce($providersFromDirectories->merge($initialValue)->toArray())
        );
    }

    public function vendorPublish(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getVendorPublish')->reduce($initialValue)
        );
    }

    public function updateConfig(array $initialValue = []): array
    {
        return $this->call('getUpdateConfig')->reduce($initialValue);
    }

    public function installationCommands(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getInstallationCommands')->reduce($initialValue)
        );
    }

    public function wrapUpCommands(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getWrapUpCommands')->reduce($initialValue)
        );
    }

    public function composerPackages(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getComposerPackages')->reduce($initialValue)
        )->unique(fn ($package) => collect(explode(' ', $package))->first());
    }

    public function composerDevPackages(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getComposerDevPackages')->reduce($initialValue)
        )->unique(fn ($package) => collect(explode(' ', $package))->first());
    }

    public function allowedComposerPlugins(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getAllowedComposerPlugins')->reduce($initialValue)
        );
    }

    public function gitIgnore(array $initialValue = []): Collection
    {
        return $this->uniqueCollection(
            $this->call('getGitIgnore')->reduce($initialValue)
        );
    }

    public function composerScripts(array $initialValue = []): Collection
    {
        return collect($this->call('getComposerScripts')->reduce($initialValue));
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

    protected function getComposerPackagesFromPlugin(InstallationResult $result, Installable $plugin): array
    {
        if (count($plugin->requiredComposerPackages())) {
            return $plugin->requiredComposerPackages();
        }

        if (
            count($plugin->anyRequiredComposerPackages())
            && count(
                array_intersect($plugin->anyRequiredComposerPackages(), $result->getComposerPackages())
            ) === 0
        ) {
            return [$plugin->anyRequiredComposerPackages()[0]];
        }

        return [];
    }

    protected function getNpmPackagesFromPlugin(InstallationResult $result, Installable $plugin): array
    {
        if (count($plugin->requiredNpmPackages())) {
            return $plugin->requiredNpmPackages();
        }

        if (
            count($plugin->anyRequiredNpmPackages())
            && count(
                array_intersect($plugin->anyRequiredNpmPackages(), $result->getNpmPackages())
            ) === 0
        ) {
            return [$plugin->anyRequiredNpmPackages()[0]];
        }

        return [];
    }

    protected function uniqueCollection(array $arr): Collection
    {
        return collect($arr)->unique()->values();
    }
}
