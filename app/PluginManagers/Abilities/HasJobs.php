<?php

namespace Bellows\PluginManagers\Abilities;

use Bellows\Data\PluginJob;

trait HasJobs
{
    /**
     * @return PluginJob[]
     */
    public function jobs(): array
    {
        return $this->call('getJobs')->reduce([]);
    }
}
