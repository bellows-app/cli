<?php

namespace Bellows\ServerProviders;

use Bellows\Contracts\ServerProviderServer;
use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Illuminate\Support\Collection;

interface ServerProviderInterface
{
    public function setCredentials(): void;

    public function getServers(): Collection;

    public function getServer(): ?ServerProviderServer;

    public function getServerDeployTargetFromServer(ServerInterface $server): ServerDeployTarget;
}
