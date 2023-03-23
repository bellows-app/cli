<?php

namespace Tests;

use Bellows\Config;
use Bellows\Data\ForgeServer;
use Bellows\Data\ProjectConfig;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $projectDir = __DIR__ . '/stubs/plugins/default';

        $this->app->bind(
            Config::class,
            fn () => new Config(__DIR__ . '/stubs/config'),
        );

        $this->app->bind(ProjectConfig::class, function () use ($projectDir) {
            return new ProjectConfig(
                isolatedUser: 'tester',
                repositoryUrl: 'bellows/tester',
                repositoryBranch: 'main',
                phpVersion: '8.1',
                phpBinary: 'php81',
                projectDirectory: $projectDir,
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

        file_put_contents($projectDir . '/.env', '');
        $composer = json_decode(file_get_contents($projectDir . '/composer.json'), true);
        $composer['require'] = ['php' => '^8.1'];
        file_put_contents($projectDir . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT));

        $packages = json_decode(file_get_contents($projectDir . '/package.json'), true);
        $packages['dependencies'] = [];
        $packages['devDependencies'] = [];
        file_put_contents($projectDir . '/package.json', json_encode($packages, JSON_PRETTY_PRINT));
    }

    public function plugin(): PendingPlugin
    {
        return new PendingPlugin($this, $this->app, '', []);
    }
}
