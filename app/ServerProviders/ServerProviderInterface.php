<?php

namespace Bellows\ServerProviders;

use Illuminate\Support\Collection;

interface ServerProviderInterface
{
    public function setCredentials(): void;

    public function getServer(): ?ServerInterface;

    public function getLoadBalancedSite(int $serverId): SiteInterface;

    /** @return Collection<ServerInterface> */
    public function getLoadBalancedServers(int $serverId, int $siteId): Collection;
}
