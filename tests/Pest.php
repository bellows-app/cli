<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Bellows\Artisan;
use Bellows\Config;
use Bellows\Console;
use Bellows\Data\PhpVersion;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\DeployScript;
use Bellows\Facades\Project;
use Bellows\Http;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\ServerProviders\ServerInterface;
use Tests\DuskyCommand;

uses(Tests\TestCase::class)->in('Feature');
//uses(Tests\TestCase::class)->in('Unit');
//uses(Tests\PluginTestCase::class)->group('plugin')->in('Unit/Plugins');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//     return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function pluginConstructorArgs()
{
    return [
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ];
}

function command(string $command): DuskyCommand
{
    return new DuskyCommand($command);
}

function cdTo(string $dir): void
{
    chdir(base_path('tests/' . $dir));
}

function overrideProjectConfig(array $params): void
{
    $projectConfig = ProjectConfig::from(array_merge(
        app(ProjectConfig::class)->toArray(),
        $params
    ));

    app()->bind(ProjectConfig::class, fn ()  => $projectConfig);

    Project::setConfig($projectConfig);
}

function installNpmPackage(?string $package): void
{
    if (is_null($package)) {
        return;
    }

    $projectDir = app(ProjectConfig::class)->directory;

    $packages = json_decode(file_get_contents($projectDir . '/package.json'), true);
    $packages['dependencies'][$package] = '*';

    file_put_contents($projectDir . '/package.json', json_encode($packages, JSON_PRETTY_PRINT));
}

function addNpmScript(string $script): void
{
    $projectDir = app(ProjectConfig::class)->directory;

    $packages = json_decode(file_get_contents($projectDir . '/package.json'), true);
    $packages['scripts'][$script] = '*';

    file_put_contents($projectDir . '/package.json', json_encode($packages, JSON_PRETTY_PRINT));
}

function installComposerPackage(?string $package): void
{
    if (is_null($package)) {
        return;
    }

    $projectDir = app(ProjectConfig::class)->directory;

    $composer = json_decode(file_get_contents($projectDir . '/composer.json'), true);
    $composer['require'][$package] = '*';

    file_put_contents($projectDir . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT));
}

function setPhpVersionForProject(string $phpVersion): void
{
    $projectDir = app(ProjectConfig::class)->directory;

    $composer = json_decode(file_get_contents($projectDir . '/composer.json'), true);
    $composer['require']['php'] = $phpVersion;

    file_put_contents($projectDir . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT));
}

function setInEnv(string $key, string $value): void
{
    $projectDir = app(ProjectConfig::class)->directory;

    $env = file_get_contents($projectDir . '/.env');
    $env .= "\n{$key}={$value}";

    file_put_contents($projectDir . '/.env', $env);
}

function servers(): array
{
    return [
        server([
            'id'   => 1,
            'name' => 'testserver',
        ]),
        server([
            'id'   => 2,
            'name' => 'testserver2',
        ]),
    ];
}

function server(array $params): array
{
    return array_merge(
        [
            'credential_id'      => fake()->randomNumber,
            'name'               => fake()->word,
            'type'               => 'app',
            'provider'           => 'ocean2',
            'provider_id'        => (string) fake()->randomNumber,
            'size'               => '01',
            'region'             => 'New York 1',
            'ubuntu_version'     => '22.04',
            'db_status'          => null,
            'redis_status'       => null,
            'php_version'        => 'php81',
            'php_cli_version'    => 'php81',
            'database_type'      => 'mysql8',
            'ip_address'         => fake()->ipv4,
            'ssh_port'           => 22,
            'private_ip_address' => fake()->ipv4,
            'local_public_key'   => 'ssh-rsa TEST root@projects',
            'blackfire_status'   => null,
            'papertrail_status'  => null,
            'revoked'            => false,
            'created_at'         => '2022-08-26T14:07:19.000000Z',
            'is_ready'           => true,
            'tags'               => [],
            'network'            => [],
        ],
        $params
    );
}

function site(array $params): array
{
    return array_merge(
        [
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
        ],
        $params
    );
}

function deployScript(string $type = 'default'): bool|string
{
    return file_get_contents(__DIR__ . "/stubs/deploy-scripts/{$type}.bash");
}

function envFile(string $type = 'default'): bool|string
{
    return file_get_contents(__DIR__ . "/stubs/env/{$type}.ini");
}
