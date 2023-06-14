<?php

namespace Bellows\PluginManagers\Abilities;

use Bellows\PluginSdk\Data\SecurityRule;

trait HasSecurityRules
{
    /**
     * @return SecurityRule[]
     */
    public function securityRules(): array
    {
        return $this->call('getSecurityRules')->reduce([]);
    }
}
