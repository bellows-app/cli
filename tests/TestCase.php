<?php

namespace Tests;

use Bellows\Config;
use Bellows\Data\ProjectConfig;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;
use Spatie\Fork\Fork;

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

        $this->app->bind(Fork::class, fn () => new class extends Fork
        {
            public function run(callable ...$callables): array
            {
                // We don't want to actually fork the process for the tests,
                // just run the actual task and return the result.
                return [$callables[0]()];
            }
        });

        $this->app->bind(
            ProjectConfig::class,
            fn () => new ProjectConfig(
                isolatedUser: 'tester',
                repositoryUrl: 'bellows/tester',
                repositoryBranch: 'main',
                phpVersion: '8.1',
                phpBinary: 'php81',
                projectDirectory: $projectDir,
                domain: 'bellowstester.com',
                appName: 'Bellows Tester',
                secureSite: true,
            )
        );

        $this->resetStubFiles($projectDir);
    }

    protected function tearDown(): void
    {
        $this->resetStubFiles(app(ProjectConfig::class)->projectDirectory);

        parent::tearDown();
    }

    public function plugin(): PendingPlugin
    {
        return new PendingPlugin($this, $this->app, '', []);
    }

    protected function resetStubFiles(string $projectDir): void
    {
        // Reset the .env file
        file_put_contents($projectDir . '/.env', '');

        // Reset the composer.json file
        file_put_contents($projectDir . '/composer.json', <<<'JSON'
{
    "name": "bellows/cli",
    "type": "project",
    "description": "Test file for Bellows CLI",
    "keywords": [
        "cli",
        "bellows"
    ],
    "license": "MIT",
    "require": {
        "php": "^8.1"
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
JSON);

        // Reset the package.json file
        file_put_contents($projectDir . '/package.json', <<<'JSON'
{
    "private": true,
    "scripts": [],
    "devDependencies": [],
    "dependencies": []
}
JSON);
    }
}
