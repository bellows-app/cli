<?php

namespace Tests;

abstract class PluginTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(
            \Bellows\ServerProviders\ServerInterface::class,
            fn () => app(\Tests\Fakes\FakeServer::class),

        );
        $this->app->bind(
            \Bellows\ServerProviders\SiteInterface::class,
            fn () => app(\Tests\Fakes\FakeSite::class),
        );

        $this->app->bind(
            \Bellows\Data\ForgeServer::class,
            fn () => \Bellows\Data\ForgeServer::from(server([
                'id'         => 123,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
        );

        $this->app->bind(
            \Bellows\Data\ForgeSite::class,
            fn () => \Bellows\Data\ForgeSite::from(site([
                'id'   => 123,
                'name' => app(\Bellows\Data\ProjectConfig::class)->domain ?? 'testsite.com',
            ])),
        );
    }
}
