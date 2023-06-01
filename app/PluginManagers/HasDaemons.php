<?php

namespace Bellows\PluginManagers;

use Bellows\Data\PluginDaemon;

trait HasDaemons
{
    /**
     * @return PluginDaemon[]
     */
    public function daemons(): array
    {
        return $this->call('getDaemons')->reduce([]);
    }
}
