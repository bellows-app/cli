<?php

namespace Bellows\PluginManagers;

use Bellows\Config;
use Bellows\Contracts\DeployableManager;
use Bellows\PluginManagers\Abilities\CallsMethodsOnPlugins;
use Bellows\PluginManagers\Abilities\ConfiguresPlugins;
use Bellows\PluginManagers\Abilities\HasDaemons;
use Bellows\PluginManagers\Abilities\HasEnvironmentVariables;
use Bellows\PluginManagers\Abilities\HasJobs;
use Bellows\PluginManagers\Abilities\HasSecurityRules;
use Bellows\PluginManagers\Abilities\HasWorkers;
use Bellows\PluginManagers\Abilities\LoadsPlugins;
use Bellows\PluginManagers\Abilities\UpdatesDeploymentScripts;
use Bellows\PluginManagers\Abilities\WrapsUp;
use Bellows\PluginManagers\Helpers\EnabledForDeployment;
use Bellows\PluginSdk\Contracts\Database;
use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\Repository;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Plugin;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;
use ReflectionClass;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

class LaunchManager implements DeployableManager
{
    use CallsMethodsOnPlugins,
        ConfiguresPlugins,
        HasDaemons,
        HasEnvironmentVariables,
        HasJobs,
        HasSecurityRules,
        HasWorkers,
        UpdatesDeploymentScripts,
        WrapsUp,
        LoadsPlugins;

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
        $plugins = $this->getAllPlugins(Scope::raw(Deployable::class));

        [$databasePlugins, $plugins] = $plugins->partition(
            fn (Plugin $plugin) => (new ReflectionClass($plugin))->implementsInterface(Database::class)
        );

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

        if ($databasePlugins->isNotEmpty()) {
            $databaseSelection = Console::choice(
                'Which database do you want to use?',
                $databasePlugins->values()->map(fn (Plugin $p) => $p->getName())->concat(['None'])->toArray()
            );

            if ($databaseSelection !== 'None') {
                $databasePlugin = $databasePlugins->first(fn (Plugin $p) => $p->getName() === $databaseSelection);

                Console::info("Configuring <comment>{$databasePlugin->getName()}</comment> plugin...");
                Console::newLine();

                $this->pluginResults->push($databasePlugin->deploy());
            }
        }
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
