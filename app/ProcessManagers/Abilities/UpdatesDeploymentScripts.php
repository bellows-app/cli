<?php

namespace Bellows\ProcessManagers\Abilities;

trait UpdatesDeploymentScripts
{
    public function updateDeployScript(): string
    {
        return $this->call('getUpdateDeployScript')->reduce('');
    }
}
