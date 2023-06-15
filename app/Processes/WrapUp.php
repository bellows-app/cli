<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Closure;

class WrapUp
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $installation->manager->wrapUp();

        return $next($installation);
    }
}
