<?php

namespace Bellows\ProcessManagers\Abilities;

use Bellows\PluginSdk\Plugin;
use Bellows\ProcessManagers\CommandRunner;

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
