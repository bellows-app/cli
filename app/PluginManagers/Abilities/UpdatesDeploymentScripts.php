<?php

namespace Bellows\PluginManagers\Abilities;

trait UpdatesDeploymentScripts
{
    public function updateDeployScript(string $deployScript): string
    {
        return $this->call('getUpdateDeployScript')->reduce($deployScript);
    }
}
