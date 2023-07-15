<?php

namespace Bellows\ProcessManagers\Abilities;

use Bellows\ProcessManagers\CommandRunner;
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
