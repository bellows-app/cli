<?php

namespace Bellows\ServerProviders;

interface ServerProviderInterface
{
    public function setCredentials(): void;

    public function getServer(): ?ServerInterface;

    public function getConfigFromServer(ServerInterface $server): ConfigInterface;
}
