<?php

namespace Bellows\PluginManagers;

trait UpdatesDeploymentScripts
{
    public function updateDeployScript(string $deployScript): string
    {
        return $this->call('getUpdateDeployScript')->reduce($deployScript);
    }
}
