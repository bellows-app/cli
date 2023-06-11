<?php

namespace Bellows\Safety;

use Bellows\Facades\Console;
use Bellows\PluginSdk\Plugin;

trait PreventsCallingFromPlugin
{
    protected function preventCallingFromPlugin(string $method)
    {
        // TODO: Can we hop up one level in the debug backtrace to get the method name? Not sure.
        // This should never be called from a plugin, it would cause chaos. If it is, exit immediately.
        $calledFromPlugin = collect(debug_backtrace())
            ->filter(fn ($trace) => array_key_exists('object', $trace))
            ->first(fn ($trace) => is_subclass_of($trace['object'], Plugin::class)) !== null;

        if ($calledFromPlugin) {
            Console::error("The {$method} should not be called from a plugin.");
            exit;
        }
    }
}
