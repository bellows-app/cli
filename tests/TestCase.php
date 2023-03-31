<?php

namespace Tests;

use Bellows\Config;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
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

        // Reset the .env file
        file_put_contents($projectDir . '/.env', '');

        // Reset the composer.json file
        $composer = json_decode(file_get_contents($projectDir . '/composer.json'), true);
        $composer['require'] = ['php' => '^8.1'];

        file_put_contents($projectDir . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT));

        // Reset the package.json file
        $packages = json_decode(file_get_contents($projectDir . '/package.json'), true);
        $packages['dependencies'] = [];
        $packages['devDependencies'] = [];
        $packages['scripts'] = [];

        file_put_contents($projectDir . '/package.json', json_encode($packages, JSON_PRETTY_PRINT));

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
    }

    public function plugin(): PendingPlugin
    {
        return new PendingPlugin($this, $this->app, '', []);
    }
}
