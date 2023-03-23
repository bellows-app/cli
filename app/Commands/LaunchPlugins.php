<?php

namespace Bellows\Commands;

use Bellows\Config;
use Bellows\PluginManager;
use LaravelZero\Framework\Commands\Command;

class LaunchPlugins extends Command
{
    protected $signature = 'launch:plugins {action? : blacklist, whitelist}';

    protected $description = 'Configure which plugins should run when launching a project.';

    protected Config $config;

    protected PluginManager $pluginManager;

    public function handle(Config $config, PluginManager $pluginManager)
    {
        $action = $this->argument('action');

        $this->config = $config;
        $this->pluginManager = $pluginManager;

        $validActions = collect([
            [
                'title'       => 'Blacklist Plugins',
                'description' => 'Specify plugins that should <info>never</info> when running the "launch" command',
                'value'       => 'blacklist',
            ],
            [
                'title'       => 'Whitelist Plugins',
                'description' => 'Specify the <info>only</info> plugins that should run when running the "launch" command',
                'value'       => 'whitelist',
            ],
        ]);

        $this->warn('');

        if (!$validActions->pluck('value')->contains($action)) {
            $validActions->each(function ($action) {
                $this->info($action['title']);
                $this->comment($action['description']);
                $this->newLine();
            });

            $action = $this->choice('What do you want to do?', $validActions->pluck('title')->toArray());
            $action = $validActions->where('title', $action)->first()['value'];
        }

        switch ($action) {
            case 'blacklist':
                $this->blacklistPlugins($config);
                break;
            case 'whitelist':
                $this->whitelistPlugins($config);
                break;
        }
    }

    protected function blacklistPlugins()
    {
        $blacklist = $this->config->get('plugins.launch.blacklist', []);
        $whitelist = $this->config->get('plugins.launch.whitelist', []);

        if (count($whitelist)) {
            $this->warn('You have a plugin whitelist set, you cannot blacklist plugins when you have a whitelist set.');

            if (!$this->confirm('Do you want to clear your whitelist and start a blacklist instead?')) {
                $this->info('Ok, aborting.');

                return;
            }

            $this->config->set('plugins.launch.whitelist', []);

            $this->newLine();
        }

        $allPlugins = $this->pluginManager->getAllAvailablePluginNames()->map(fn ($plugin) => [
            'name'    => class_basename($plugin),
            'value'   => $plugin,
            'enabled' => !in_array($plugin, $blacklist),
        ])->sortByDesc('enabled');

        $this->table(
            ['Enabled', 'Plugin'],
            $allPlugins->map(fn ($plugin) => [
                $plugin['enabled'] ? '<info>✓</info>' : '<warning>✗</warning>',
                $plugin['name'],
            ])->toArray()
        );

        do {
            $toToggle = $this->anticipate(
                'Plugin to toggle',
                $allPlugins->pluck('name')->toArray(),
            );
        } while (!$allPlugins->pluck('name')->contains($toToggle));

        $fullToToggle = $allPlugins->where('name', $toToggle)->first()['value'];

        $this->newLine(2);

        if (in_array($fullToToggle, $blacklist)) {
            $blacklist = collect($blacklist)->filter(fn ($plugin) => $plugin !== $fullToToggle)->values()->toArray();

            $this->miniTask('Removed', $toToggle);
        } else {
            $blacklist[] = $fullToToggle;

            $this->miniTask('Added', $toToggle);
        }

        $this->newLine(2);

        if (count($blacklist) === 0) {
            $this->config->remove('plugins.launch.blacklist');
        } else {
            $this->config->set('plugins.launch.blacklist', $blacklist);
        }

        $this->blacklistPlugins();
    }

    protected function whitelistPlugins()
    {
        $blacklist = $this->config->get('plugins.launch.blacklist', []);
        $whitelist = $this->config->get('plugins.launch.whitelist', []);

        if (count($blacklist)) {
            $this->warn('You have a plugin blacklist set, you cannot whitelist plugins when you have a blacklist set.');

            if (!$this->confirm('Do you want to clear your blacklist and start a whitelist instead?')) {
                $this->info('Ok, aborting.');

                return;
            }

            $this->config->set('plugins.launch.blacklist', []);

            $this->newLine();
        }

        $allPlugins = $this->pluginManager->getAllAvailablePluginNames()->map(fn ($plugin) => [
            'name'    => class_basename($plugin),
            'value'   => $plugin,
            'enabled' => count($whitelist) === 0 || in_array($plugin, $whitelist),
        ])->sortByDesc('enabled');

        if (count($whitelist) === 0) {
            $this->comment('You have no whitelist set, all plugins will be enabled.');
        }

        $this->table(
            ['Enabled', 'Plugin'],
            $allPlugins->map(fn ($plugin) => [
                $plugin['enabled'] ? '<info>✓</info>' : '<warning>✗</warning>',
                $plugin['name'],
            ])->toArray()
        );

        do {
            $toToggle = $this->anticipate(
                'Plugin to toggle',
                $allPlugins->pluck('name')->toArray(),
            );
        } while (!$allPlugins->pluck('name')->contains($toToggle));

        $fullToToggle = $allPlugins->where('name', $toToggle)->first()['value'];

        $this->newLine(2);

        if (in_array($fullToToggle, $whitelist)) {
            $whitelist = collect($whitelist)->filter(fn ($plugin) => $plugin !== $fullToToggle)->values()->toArray();

            $this->miniTask('Removed', $toToggle);
        } else {
            $whitelist[] = $fullToToggle;

            $this->miniTask('Added', $toToggle);
        }

        $this->newLine(2);

        if (count($whitelist) === 0) {
            $this->config->remove('plugins.launch.whitelist');
        } else {
            $this->config->set('plugins.launch.whitelist', $whitelist);
        }

        $this->whitelistPlugins();
    }
}
