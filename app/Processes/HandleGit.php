<?php

namespace Bellows\Processes;

use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\Git\Git;
use Closure;

class HandleGit
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        $installation->manager->gitIgnore(
            $installation->config->get(KickoffConfigKeys::GIT_IGNORE)
        )->whenNotEmpty(fn ($files) => Git::ignore($files));

        return $next($installation);
    }
}
