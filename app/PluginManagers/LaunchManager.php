<?php

namespace Bellows\PluginManagers;

use Bellows\Config;
use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\Facades\Console;
use Bellows\PluginManagers\Abilities\CallsMethodsOnPlugins;
use Bellows\PluginManagers\Abilities\ConfiguresPlugins;
use Bellows\PluginManagers\Abilities\DealsWithServers;
use Bellows\PluginManagers\Abilities\HasDaemons;
use Bellows\PluginManagers\Abilities\HasEnvironmentVariables;
use Bellows\PluginManagers\Abilities\HasJobs;
use Bellows\PluginManagers\Abilities\HasWorkers;
use Bellows\PluginManagers\Abilities\LoadsPlugins;
use Bellows\PluginManagers\Abilities\UpdatesDeploymentScripts;
use Bellows\PluginManagers\Abilities\WrapsUp;
use Bellows\PluginManagers\Helpers\EnabledForDeployment;
use Bellows\PluginSdk\Contracts\Launchable;
use Bellows\PluginSdk\Plugin;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;
use ReflectionClass;

class LaunchManager
{
    use LoadsPlugins,
        HasDaemons,
        HasWorkers,
        HasJobs,
        WrapsUp,
        UpdatesDeploymentScripts,
        HasEnvironmentVariables,
        DealsWithServers,
        ConfiguresPlugins,
        CallsMethodsOnPlugins,
        DealsWithServers;

    /** @var Collection<\Bellows\PluginSdk\PluginResults\DeployResult> */
    protected Collection $pluginResults;

    public function __construct(
        protected Config $config,
        protected array $pluginPaths = [],
    ) {
        $this->setPluginPaths();
    }

    public function setActive()
    {
        $plugins = $this->getAllPlugins(Scope::raw(Launchable::class))->filter(
            fn (Plugin $plugin) => (new ReflectionClass($plugin))->implementsInterface(Scope::raw(Launchable::class))
        )->values();

        $decisionMaker = new EnabledForDeployment();

        $autoDecision = $plugins
            ->filter(fn (Plugin $plugin) => $decisionMaker->hasADefaultDecision($plugin))
            ->sortByDesc(fn (Plugin $plugin) => $decisionMaker->getDecision($plugin)->enabled);

        Console::table(
            ['', 'Plugin', 'Reason'],
            $autoDecision->map(fn (Plugin $p) => [
                $decisionMaker->getDecision($p)->enabled ? '<info>✓</info>' : '<warning>✗</warning>',
                $p->getName(),
                $decisionMaker->getDecision($p)->reason,
            ])->toArray(),
        );

        $defaultsAreGood = $autoDecision->count() > 0 ? Console::confirm('Continue with defaults?', true) : false;

        $this->pluginResults = $plugins->map(function (Plugin $p) use ($defaultsAreGood, $decisionMaker) {
            // Usually I would filter()->filter() but I want to keep the context of what is being asked of the user here
            // So if there is a "Do you want to enable ____?" then just answer questions via the setup method instead of
            // prompting if the plugin should be enabled and then later asking how it should be configured
            if (
                $defaultsAreGood
                && $decisionMaker->hasADefaultDecision($p)
                && !$decisionMaker->getDecision($p)->enabled
            ) {
                return false;
            }

            if (
                !$decisionMaker->hasADefaultDecision($p)
                && !Console::confirm(
                    "Enable <comment>{$p->getName()}</comment> for this launch?",
                    $decisionMaker->getDefaultForConfirmation($p)
                )
            ) {
                return false;
            }

            Console::info("Configuring <comment>{$p->getName()}</comment> plugin...");
            Console::newLine();

            return $p->deploy();
        })->filter()->values();
    }

    public function createSiteParams(array $initialValue = []): array
    {
        return $this->call('getCreateSiteParams')->reduce($initialValue);
    }

    public function installRepoParams(array $initialValue = []): array
    {
        return $this->call('getInstallRepoParams')->reduce($initialValue);
    }
}
