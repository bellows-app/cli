<?php

namespace App\Bellows;

use App\Bellows\Data\Daemon;
use App\Bellows\Data\Job;
use App\Bellows\Data\ProjectConfig;
use App\Bellows\Data\Worker;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PluginManager
{
    public function __construct(
        protected Console $console,
        protected Collection $activePlugins,
    ) {
    }

    public function setActive()
    {
        $plugins = collect(ClassFinder::getClassesInNamespace('App\Plugins'))
            ->filter(fn ($plugin) => with(new \ReflectionClass($plugin))->isInstantiable())
            ->values()
            ->map(fn (string $plugin) => app($plugin))
            ->sortByDesc(fn (Plugin $plugin) => $plugin->priority);

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
            $enabled = $defaultsAreGood && $p->hasADefaultEnabledDecision()
                ? $p->getDefaultEnabled()->enabled
                : $p->enabled();

            if (!$enabled) {
                return false;
            }

            $this->console->info("Configuring <comment>{$p->getName()}</comment> plugin...");

            $this->call('setup', $p)->run();

            return true;
        })->values();
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

    public function setEnvironmentVariables(): array
    {
        return $this->call('setEnvironmentVariables')->reduce([]);
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $this->call('updateDeployScript')->reduce($deployScript);
    }

    public function daemons(ProjectConfig $config)
    {
        $this->call('daemons')
            ->run()
            ->flatMap(fn ($r) => $r)
            ->ray()
            ->each(
                fn (Daemon $daemon) => Http::forgeServer()->post(
                    'daemons',
                    [
                        'command'   => $daemon->command,
                        'user'      => $daemon->user ?: $config->isolatedUser,
                        'directory' => $daemon->directory ?: "/home/{$config->isolatedUser}/{$config->domain}",
                    ],
                )
            );
    }

    public function workers(ProjectConfig $config)
    {
        $this->call('workers')
            ->run()
            ->flatMap(fn ($r) => $r)
            ->ray()
            ->each(
                fn (Worker $worker) => ray(Http::forgeSite()->post(
                    'workers',
                    array_merge(
                        ['php_version'  => $config->phpVersion],
                        $worker->toArray()
                    ),
                )->json())
            );
    }

    public function jobs(ProjectConfig $config)
    {
        $this->call('jobs')
            ->run()
            ->flatMap(fn ($r) => $r)
            ->ray()
            ->each(
                fn (Job $job) => Http::forgeServer()->post(
                    'jobs',
                    array_merge(
                        ['user' => $config->isolatedUser],
                        $job->toArray()
                    ),
                )
            );
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
