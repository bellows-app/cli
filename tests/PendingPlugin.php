<?php

namespace Tests;

use Bellows\Console;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\ServerProviders\Forge\Forge;
use Bellows\ServerProviders\Forge\Server;
use Bellows\ServerProviders\Forge\Site;
use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Mockery;
use Mockery\MockInterface;

class PendingPlugin extends PendingCommand
{
    protected MockInterface $serverMock;

    protected MockInterface $siteMock;

    public function setup(): static
    {
        $this->hasExecuted = true;

        $this->mockConsoleOutput();

        Http::fake([
            Forge::API_URL . '/user' => Http::response(),
        ]);

        app(Forge::class)->setCredentials();

        $this->app->bind(
            Server::class,
            fn () => $this->serverMock ?? new Server(
                app(ForgeServer::class),
                app(Console::class)
            )
        );

        $this->app->bind(
            Site::class,
            fn () => $this->siteMock ?? new Site(
                app(ForgeSite::class),
                app(ForgeServer::class),
                app(Console::class)
            )
        );

        return $this;
    }

    public function mockServer(Closure $callback): static
    {
        $mock = Mockery::mock(Server::class);

        $callback($mock);

        $this->serverMock = $mock;

        return $this;
    }

    public function mockSite(Closure $callback): static
    {
        $mock = Mockery::mock(Site::class);

        $callback($mock);

        $this->siteMock = $mock;

        return $this;
    }

    public function validate()
    {
        $this->verifyExpectations();
        $this->flushExpectations();
    }

    public function __destruct()
    {
        // Override this so the `run` method never fires
    }
}
