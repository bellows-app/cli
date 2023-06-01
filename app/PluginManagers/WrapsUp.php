<?php

namespace Bellows\PluginManagers;

trait WrapsUp
{
    public function wrapUp(): void
    {
        // TODO: Refactor this to work better.
        $this->call('getWrapUp')->run()->filter()->each(fn ($cb) => $cb());
    }
}
