<?php

namespace Bellows\ServerProviders;

use Illuminate\Support\Collection;

interface ServerProviderInterface
{
    public function setCredentials(): void;

    public function getServers(): Collection;

    public function getServer(): ?ServerInterface;

    public function getConfigFromServer(ServerInterface $server): ConfigInterface;
}
