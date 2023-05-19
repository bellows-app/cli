<?php

namespace Bellows;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\PluginDaemon;
use Bellows\Data\PluginJob;
use Bellows\Data\PluginWorker;
use Bellows\Facades\Console;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;
use ReflectionClass;
use Spatie\StructureDiscoverer\Discover;

class PluginManager implements PluginManagerInterface
{
    protected Collection $activePlugins;

    // If load balancing, the primary site, if not, the same as the $site property
    protected SiteInterface $primarySite;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerInterface $primaryServer;

    public function __construct(
        protected Config $config,
        protected array $pluginPaths = [],
    ) {
        if (count($this->pluginPaths) === 0) {
            // If nothing was passed in, default to app_path()
            $this->pluginPaths = [app_path()];
        }
    }

    public function setActiveForDeploy(SiteInterface $site)
    {
        $plugins = $this->getAllPlugins()->filter(
            fn (Plugin $plugin) => (new ReflectionClass($plugin))->implementsInterface(Deployable::class)
        )->values();

        [$enabledBasedOnProject, $noAutoDecision] = $plugins->partition(fn (Plugin $plugin) => $plugin->hasADefaultEnabledDecision());

        $enabledBasedOnProject = $enabledBasedOnProject->filter(fn (Plugin $plugin) => $plugin->getDefaultEnabled()->enabled);

        // Loop through each site, and check if the plugin is deployable for the site
        $enabled = $enabledBasedOnProject->map(
            fn (Plugin $p) => $p->setSite($site)
        )->map(
            fn (Plugin $p) => $p->setServer($site->getServerProvider())
        )->filter(fn ($p) => $p->canDeploy())->values();

        $optional = $noAutoDecision->map(
            fn (Plugin $p) => $p->setSite($site)
        )->map(
            fn (Plugin $p) => $p->setServer($site->getServerProvider())
        )->filter(fn ($p) => $p->canDeploy())->values();

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
            false, // Required false so that it doesn't auto-select if there is only one (usually the desired behavior)
        );

        $this->activePlugins = $allPlugins->filter(fn ($p) => in_array($p->getName(), $response))
            ->filter(fn ($p) => $this->configure($p, 'deploy', true));
    }

    public function setActiveForLaunch()
    {
        $plugins = $this->getAllPlugins()->filter(
            fn (Plugin $plugin) => (new ReflectionClass($plugin))->implementsInterface(Launchable::class)
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

        $this->activePlugins = $plugins->filter(function (Plugin $p) use ($defaultsAreGood) {
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

    public function getAllAvailablePluginNames(): Collection
    {
        return collect($this->pluginPaths)
            ->flatMap(fn (string $path) => Discover::in($path)->extending(Plugin::class)->get())
            ->filter(fn ($plugin) => with(new ReflectionClass($plugin))->isInstantiable())
            ->values();
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

    public function environmentVariables(): array
    {
        return $this->call('environmentVariables')->reduce([]);
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $this->call('updateDeployScript')->reduce($deployScript);
    }

    /**
     * @return Collection<PluginDaemon>
     */
    public function daemons(): Collection
    {
        return $this->call('daemons')->run()->flatMap(fn ($r) => $r)->filter()->values();
    }

    /**
     * @return Collection<PluginWorker>
     */
    public function workers(): Collection
    {
        return $this->call('workers')->run()->flatMap(fn ($r) => $r)->filter()->values();
    }

    /**
     * @return Collection<PluginJob>
     */
    public function jobs(): Collection
    {
        return $this->call('jobs')->run()->flatMap(fn ($r) => $r)->filter()->values();
    }

    public function wrapUp(): void
    {
        $this->call('wrapUp')->run();
    }

    public function setPrimarySite(?SiteInterface $site): void
    {
        if ($site) {
            $this->primarySite = $site;
        }
    }

    public function setPrimaryServer(ServerInterface $server): void
    {
        $this->primaryServer = $server;
    }

    public function setSite(SiteInterface $site): void
    {
        $this->call('setSite')->withArgs($site)->run();
    }

    public function setServer(ServerInterface $server): void
    {
        $this->call('setServer')->withArgs($server)->run();
    }

    protected function getAllPlugins(): Collection
    {
        $blacklist = $this->config->get('plugins.launch.blacklist', []);
        $whitelist = $this->config->get('plugins.launch.whitelist', []);

        return $this->getAllAvailablePluginNames()
            ->filter(function (string $plugin) use ($blacklist, $whitelist) {
                if (count($blacklist) > 0) {
                    return !in_array($plugin, $blacklist);
                }

                if (count($whitelist) > 0) {
                    return in_array($plugin, $whitelist);
                }

                return true;
            })
            ->values()
            ->map(fn (string $plugin) => app($plugin))
            ->each(fn (Plugin $p) => isset($this->primarySite) ? $p->setPrimarySite($this->primarySite) : null)
            ->each(fn (Plugin $p) => $p->setPrimaryServer($this->primaryServer))
            ->sortBy([
                fn (Plugin $a, Plugin $b) => $b->priority <=> $a->priority,
                fn (Plugin $a, Plugin $b) => get_class($a) <=> get_class($b),
            ]);
    }

    protected function configure(Plugin $p, string $method, ?bool $isEnabled = null): bool
    {
        Console::info("Configuring <comment>{$p->getName()}</comment> plugin...");
        Console::newLine();

        $enabled = $isEnabled ?? $p->enabled();

        if ($enabled) {
            $result = $this->call($method, $p)->run();

            if (is_bool($result->first())) {
                return $result->first();
            }
        }

        return $enabled;
    }

    protected function call(string $method, Plugin $plugin = null): PluginCommandRunner
    {
        return new PluginCommandRunner($plugin ? collect([$plugin]) : $this->activePlugins, $method);
    }
}
