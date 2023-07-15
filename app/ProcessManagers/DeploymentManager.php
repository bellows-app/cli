<?php

namespace Bellows\ProcessManagers;

use Bellows\Config;
use Bellows\Contracts\DeployableManager;
use Bellows\Contracts\ServerProviderSite;
use Bellows\ProcessManagers\Abilities\CallsMethodsOnPlugins;
use Bellows\ProcessManagers\Abilities\ConfiguresPlugins;
use Bellows\ProcessManagers\Abilities\HasDaemons;
use Bellows\ProcessManagers\Abilities\HasEnvironmentVariables;
use Bellows\ProcessManagers\Abilities\HasJobs;
use Bellows\ProcessManagers\Abilities\HasSecurityRules;
use Bellows\ProcessManagers\Abilities\HasWorkers;
use Bellows\ProcessManagers\Abilities\LoadsPlugins;
use Bellows\ProcessManagers\Abilities\UpdatesDeploymentScripts;
use Bellows\ProcessManagers\Abilities\WrapsUp;
use Bellows\ProcessManagers\Helpers\EnabledForDeployment;
use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Plugin;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;

class DeploymentManager implements DeployableManager
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

    public function setActive(ServerProviderSite $site)
    {
        $plugins = $this->getAllPlugins(Scope::raw(Deployable::class));

        $decisionMaker = new EnabledForDeployment();

        [$enabledBasedOnProject, $noAutoDecision] = $plugins->partition(
            fn (Plugin $plugin) => $decisionMaker->hasADefaultDecision($plugin)
        );

        $enabledBasedOnProject = $enabledBasedOnProject->filter(
            fn (Plugin $plugin) => $decisionMaker->getDecision($plugin)->enabled
        );

        // Loop through each site, and check if the plugin is deployable for the site
        $enabled = $this->getPluginsThatShouldBeDeployed($enabledBasedOnProject, $site);
        $optional = $this->getPluginsThatShouldBeDeployed($noAutoDecision, $site);

        Console::info(
            sprintf(
                'Confirm plugins to deploy on <comment>%s</comment> (<comment>%s</comment>):',
                $site->name,
                $site->getServer()->name,
            ),
        );

        Console::newLine();

        $allPlugins = $enabled->merge($optional);

        $allChoices = $allPlugins->map(fn ($p) => $p->getName());

        $response = Console::choice(
            'Plugins',
            $allChoices->toArray(),
            $enabled->count() > 0 ? $enabled->keys()->join(',') : null,
            null,
            true,
            false, // Set "required" to false so that it doesn't auto-select if there is only one (usually the desired behavior)
        );

        $this->pluginResults = $allPlugins
            ->filter(fn ($p) => in_array($p->getName(), $response))
            ->filter(fn ($p) => $p->confirmDeploy())
            ->map(function ($p) {
                Console::info("Configuring <comment>{$p->getName()}</comment> plugin...");
                Console::newLine();

                return $p->deploy();
            })
            ->filter();
    }

    protected function getPluginsThatShouldBeDeployed(Collection $plugins, ServerProviderSite $site)
    {
        Deployment::setSite($site)->setServer($site->getServerProvider());

        return $plugins->filter(fn ($p) => $p->shouldDeploy())->values();
    }
}
