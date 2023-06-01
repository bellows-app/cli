<?php

namespace Bellows\PluginManagers;

use Bellows\Config;
use Bellows\Facades\Console;
use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Plugin;
use Bellows\ServerProviders\SiteInterface;
use Bellows\Util\Scope;
use Illuminate\Support\Collection;
use ReflectionClass;

class DeploymentManager
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
        CallsMethodsOnPlugins;

    /** @var Collection<\Bellows\PluginSdk\PluginResults\DeployResult> */
    protected Collection $pluginResults;

    public function __construct(
        protected Config $config,
        protected array $pluginPaths = [],
    ) {
        $this->setPluginPaths();
    }

    public function setActive(SiteInterface $site)
    {
        $plugins = $this->getAllPluginsWithSiteAndServer()->filter(
            fn (Plugin $plugin) => (new ReflectionClass($plugin))->implementsInterface(Scope::raw(Deployable::class))
        )->values();

        // TODO: This is no longer clear, is this the right verbiage?
        // Installable completely ignores this, so it's not enabled or disabled, really.
        [$enabledBasedOnProject, $noAutoDecision] = $plugins->partition(fn (Plugin $plugin) => $plugin->hasADefaultEnabledDecision());

        $enabledBasedOnProject = $enabledBasedOnProject->filter(fn (Plugin $plugin) => $plugin->getDefaultEnabled()->enabled);

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

        $this->pluginResults = $allPlugins->filter(
            fn ($p) => in_array($p->getName(), $response)
        )->filter(
            fn ($p) => $this->configure($p, 'deploy', true)
        );
    }

    protected function getPluginsThatShouldBeDeployed(Collection $plugins, SiteInterface $site)
    {
        return $plugins->map(fn (Plugin $p) => $p->setSite($site))
            ->map(fn (Plugin $p) => $p->setServer($site->getServerProvider()))
            ->filter(fn ($p) => $p->shouldDeploy())->values();
    }
}
