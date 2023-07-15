<?php

namespace Bellows\ProcessManagers\Abilities;

trait WrapsUp
{
    public function wrapUp(): void
    {
        $this->call('getWrapUp')->run();
    }
}
