<?php

namespace Bellows\PluginManagers\Abilities;

trait HasEnvironmentVariables
{
    public function environmentVariables(array $initialValue = []): array
    {
        return $this->call('getEnvironmentVariables')->reduce($initialValue);
    }
}
