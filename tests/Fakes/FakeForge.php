<?php

namespace Tests\Fakes;

use Bellows\ServerProviders\ConfigInterface;
use Bellows\ServerProviders\Forge\Config\LoadBalancer;
use Bellows\ServerProviders\Forge\Config\SingleServer;
use Bellows\ServerProviders\ServerInterface;
use Illuminate\Support\Collection;

class FakeForge implements \Bellows\ServerProviders\ServerProviderInterface
{

    public function getServers(): Collection
    {
        return collect();
    }

    public function getConfigFromServer(ServerInterface $server): ConfigInterface
    {
        if ($server->type === 'loadbalancer') {
            return new LoadBalancer($server);
        }

        return new SingleServer($server);
    }

    public function setCredentials(): void
    {
    }

    public function getServer(): ?ServerInterface
    {
        return app(ServerInterface::class);
    }
}
