<?php

namespace Bellows\PluginManagers\Abilities;

trait WrapsUp
{
    public function wrapUp(): void
    {
        // TODO: Refactor this to work better.
        $this->call('getWrapUp')->run()->filter()->each(fn ($cb) => $cb());
    }
}
