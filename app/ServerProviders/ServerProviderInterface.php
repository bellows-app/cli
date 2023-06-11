<?php

namespace Bellows\ServerProviders;

use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Illuminate\Support\Collection;

interface ServerProviderInterface
{
    public function setCredentials(): void;

    public function getServers(): Collection;

    public function getServer(): ?ServerInterface;

    public function getServerDeployTargetFromServer(ServerInterface $server): ServerDeployTarget;
}
