<?php

namespace Bellows;

use Illuminate\Support\Collection;
use Spatie\StructureDiscoverer\Discover;

class PluginManager
{
    protected Collection $activePlugins;

    public function __construct(
        protected Console $console,
        protected Config $config,
        protected array $pluginPaths = [],
    ) {
        if (count($this->pluginPaths) === 0) {
            // If nothing was passed in, default to app_path()
            $this->pluginPaths = [app_path()];
        }
    }

    public function setActive()
    {
        $plugins = $this->getAllPlugins();

        $autoDecision = $plugins->filter(fn (Plugin $plugin) => $plugin->hasADefaultEnabledDecision())->sortByDesc(
            fn (Plugin $plugin) => $plugin->getDefaultEnabled()->enabled
        );

        $this->console->table(
            ['', 'Plugin', 'Reason'],
            $autoDecision->map(fn (Plugin $p) => [
                $p->getDefaultEnabled()->enabled ? '<info>✓</info>' : '<warning>✗</warning>',
                get_class($p),
                $p->getDefaultEnabled()->reason,
            ])->toArray(),
        );

        $defaultsAreGood = $autoDecision->count() > 0 ? $this->console->confirm('Continue with defaults?', true) : false;

        $this->activePlugins = $plugins->filter(function (Plugin $p) use ($defaultsAreGood) {
            // Usually I would filter()->filter() but I want to keep the context of what is being asked of the user here
            // So if there is a "Do you want to enable ____?" then just answer questions via the setup method instead of
            // prompting if the plugin should be enabled and then later asking how it should be configured
            if ($defaultsAreGood && $p->hasADefaultEnabledDecision()) {
                if (!$p->getDefaultEnabled()->enabled) {
                    return false;
                }

                return $this->configure($p, true);
            }

            return $this->configure($p);
        })->values();
    }

    public function getAllAvailablePluginNames(): Collection
    {
        return collect($this->pluginPaths)
            ->flatMap(fn (string $path) => Discover::in($path)->extending(Plugin::class)->get())
            ->filter(fn ($plugin) => with(new \ReflectionClass($plugin))->isInstantiable())
            ->values();
    }

    protected function getAllPlugins(): Collection
    {
        return $this->getAllAvailablePluginNames()
            ->filter(function (string $plugin) {
                if (count($this->config->get('plugins.launch.blacklist', [])) > 0) {
                    return !in_array($plugin, $this->config->get('plugins.launch.blacklist', []));
                }

                if (count($this->config->get('plugins.launch.whitelist', [])) > 0) {
                    return in_array($plugin, $this->config->get('plugins.launch.whitelist', []));
                }

                return true;
            })
            ->values()
            ->map(fn (string $plugin) => app($plugin))
            ->sortByDesc(fn (Plugin $plugin) => $plugin->priority);
    }

    protected function configure(Plugin $p, ?bool $isEnabled = null): bool
    {
        $this->console->info("Configuring <comment>{$p->getName()}</comment> plugin...");
        $this->console->newLine();

        $enabled = $isEnabled ?? $p->enabled();

        if ($enabled) {
            $this->call('setup', $p)->run();
        }

        return $enabled;
    }

    public function createSiteParams(array $params): array
    {
        return $this->call('createSiteParams')->withArgs($params)->run()->toArray();
    }

    public function installRepoParams(array $baseParams): array
    {
        return $this->call('installRepoParams')
            ->withArgs($baseParams)
            ->run()
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
     * @return \Illuminate\Support\Collection<\Bellows\Data\Daemon>
     */
    public function daemons(): Collection
    {
        return $this->call('daemons')->run()->flatMap(fn ($r) => $r)->filter()->values();
    }

    /**
     * @return \Illuminate\Support\Collection<\Bellows\Data\Worker>
     */
    public function workers(): Collection
    {
        return $this->call('workers')->run()->flatMap(fn ($r) => $r)->filter()->values();
    }

    /**
     * @return \Illuminate\Support\Collection<\Bellows\Data\Job>
     */
    public function jobs(): Collection
    {
        return $this->call('jobs')->run()->flatMap(fn ($r) => $r)->filter()->values();
    }

    public function wrapUp()
    {
        $this->call('wrapUp')->run();
    }

    protected function call(string $method, Plugin $plugin = null)
    {
        return new PluginCommandRunner($plugin ? collect([$plugin]) : $this->activePlugins, $method);
    }
}
