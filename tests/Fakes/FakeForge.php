<?php

namespace Tests\Fakes;

use Bellows\ServerProviders\ConfigInterface;
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
        return app(ConfigInterface::class);
    }

    public function setCredentials(): void
    {
    }

    public function getServer(): ?ServerInterface
    {
        return app(ServerInterface::class);
    }
}
