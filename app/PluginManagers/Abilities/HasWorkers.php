<?php

namespace Bellows\PluginManagers\Abilities;

use Bellows\Data\PluginWorker;

trait HasWorkers
{
    /**
     * @return PluginWorker[]
     */
    public function workers(): array
    {
        return $this->call('getWorkers')->reduce([]);
    }
}
