<?php

namespace Bellows\PluginManagers\Abilities;

trait UpdatesDeploymentScripts
{
    public function updateDeployScript(): string
    {
        return $this->call('getUpdateDeployScript')->reduce('');
    }
}
