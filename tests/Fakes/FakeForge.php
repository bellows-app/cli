<?php

namespace Tests\Fakes;

use Bellows\ServerProviders\ServerInterface;

class FakeForge implements \Bellows\ServerProviders\ServerProviderInterface
{
    public function setCredentials(): void
    {
    }

    public function getServer(): ?ServerInterface
    {
        return app(ServerInterface::class);
    }
}
