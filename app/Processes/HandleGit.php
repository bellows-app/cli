<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\Git\Git;
use Closure;

class HandleGit
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $installation->manager->gitIgnore($installation->config->get('git-ignore', []))->whenNotEmpty(
            fn ($files) => Git::ignore($files)
        );

        return $next($installation);
    }
}
