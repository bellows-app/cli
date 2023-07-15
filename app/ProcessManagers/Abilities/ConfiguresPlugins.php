<?php

namespace Bellows\ProcessManagers\Abilities;

use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Plugin;

trait ConfiguresPlugins
{
    protected function configure(Plugin $p, string $method, ?bool $isEnabled = null): bool
    {
        Console::info("Configuring <comment>{$p->getName()}</comment> plugin...");
        Console::newLine();

        $enabled = $isEnabled ?? $p->enabled();

        if (!$enabled) {
            return false;
        }

        $result = $this->call($method, $p)->run();

        return is_bool($result->first()) ? $result->first() : false;
    }
}
