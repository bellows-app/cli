<?php

namespace Tests;

use Bellows\Config;
use Bellows\Data\ForgeServer;
use Bellows\Data\ProjectConfig;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Http::preventStrayRequests();

        $this->app->bind(
            Config::class,
            fn () => new Config(__DIR__ . '/stubs/config'),
        );

        $this->app->bind(ProjectConfig::class, function () {
            return new ProjectConfig(
                isolatedUser: 'tester',
                repositoryUrl: 'bellows/tester',
                repositoryBranch: 'main',
                phpVersion: '8.1',
                phpBinary: 'php81',
                projectDirectory: __DIR__ . '/stubs/plugins/default',
                domain: 'bellowstester.com',
                appName: 'Bellows Tester',
                secureSite: true,
            );
        });

        $this->app->bind(ForgeServer::class, function () {
            return ForgeServer::from([
                'id'         => 123,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ]);
        });
    }

    public function plugin(): PendingPlugin
    {
        return new PendingPlugin($this, $this->app, '', []);
    }
}
