<?php

namespace Tests;

use Bellows\Console;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\ProjectConfig;
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

        $this->app->bind(
            ForgeServer::class,
            fn () => ForgeServer::from([
                'id'         => 123,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])
        );

        $this->app->bind(
            ForgeSite::class,
            fn () => ForgeSite::from([
                'id'                  => 123,
                'name'                => app(ProjectConfig::class)->domain ?? 'testsite.com',
                'aliases'             => [],
                'directory'           => '/public',
                'wildcards'           => false,
                'status'              => 'installed',
                'repository_provider' => 'github',
                'repository_status'   => 'installed',
                'quick_deploy'        => true,
                'deployment_status'   => null,
                'project_type'        => 'php',
                'php_version'         => 'php74',
                'app'                 => null,
                'app_status'          => null,
                'slack_channel'       => null,
                'telegram_chat_id'    => null,
                'telegram_chat_title' => null,
                'teams_webhook_url'   => null,
                'discord_webhook_url' => null,
                'created_at'          => '2020-07-28 22:23:11',
                'telegram_secret'     => '/start@laravel_forge_telegram_botasdf',
                'username'            => 'forge',
                'deployment_url'      => 'https://forge.laravel.com/servers/1234/sites/12345/deploy/http?token=asdfwqfwasdvzsd',
                'is_secured'          => true,
                'tags'                => [],
            ])
        );

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
