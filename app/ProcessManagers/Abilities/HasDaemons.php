<?php

namespace Bellows\ProcessManagers\Abilities;

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
