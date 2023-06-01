<?php

namespace Bellows\PluginManagers;

use Bellows\Config;
use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\Facades\Console;
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
        CallsMethodsOnPlugins;

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
        $plugins = $this->getAllPluginsWithSiteAndServer()->filter(
            fn (Plugin $plugin) => (new ReflectionClass($plugin))->implementsInterface(Scope::raw(Launchable::class))
        )->values();

        $autoDecision = $plugins->filter(fn (Plugin $plugin) => $plugin->hasADefaultEnabledDecision())->sortByDesc(
            fn (Plugin $plugin) => $plugin->getDefaultEnabled()->enabled
        );

        Console::table(
            ['', 'Plugin', 'Reason'],
            $autoDecision->map(fn (Plugin $p) => [
                $p->getDefaultEnabled()->enabled ? '<info>✓</info>' : '<warning>✗</warning>',
                $p->getName(),
                $p->getDefaultEnabled()->reason,
            ])->toArray(),
        );

        $defaultsAreGood = $autoDecision->count() > 0 ? Console::confirm('Continue with defaults?', true) : false;

        $this->pluginResults = $plugins->filter(function (Plugin $p) use ($defaultsAreGood) {
            // Usually I would filter()->filter() but I want to keep the context of what is being asked of the user here
            // So if there is a "Do you want to enable ____?" then just answer questions via the setup method instead of
            // prompting if the plugin should be enabled and then later asking how it should be configured
            if ($defaultsAreGood && $p->hasADefaultEnabledDecision()) {
                if (!$p->getDefaultEnabled()->enabled) {
                    return false;
                }

                return $this->configure($p, 'launch', true);
            }

            return $this->configure($p, 'launch');
        })->values();
    }

    public function createSiteParams(CreateSiteParams $params): array
    {
        return $this->call('createSiteParams')
            ->withArgs($params)
            ->run()
            ->filter(fn ($arr) => count($arr) > 0)
            ->values()
            ->toArray();
    }

    public function installRepoParams(InstallRepoParams $baseParams): array
    {
        return $this->call('installRepoParams')
            ->withArgs($baseParams)
            ->run()
            ->filter(fn ($arr) => count($arr) > 0)
            ->values()
            ->toArray();
    }
}
