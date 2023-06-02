<?php

namespace Bellows\ServerProviders;

use Illuminate\Support\Collection;
use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;

interface ServerProviderInterface
{
    public function setCredentials(): void;

    public function getServers(): Collection;

    public function getServer(): ?ServerInterface;

    public function getServerDeployTargetFromServer(ServerInterface $server): ServerDeployTarget;
}
