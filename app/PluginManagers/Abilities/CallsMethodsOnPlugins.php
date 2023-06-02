<?php

namespace Bellows\PluginManagers\Abilities;

use Bellows\PluginManagers\CommandRunner;
use Bellows\PluginSdk\Plugin;

trait CallsMethodsOnPlugins
{
    protected function call(string $method, Plugin $plugin = null): CommandRunner
    {
        return new CommandRunner(
            $plugin ? collect([$plugin]) : $this->pluginResults,
            $method,
        );
    }
}
