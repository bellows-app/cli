<?php

namespace Bellows\PluginManagers\Abilities;

trait WrapsUp
{
    public function wrapUp(): void
    {
        $this->call('getWrapUp')->run();
    }
}
