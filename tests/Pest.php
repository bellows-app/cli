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

use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class)->in('Feature');

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

function cdTo(string $dir)
{
    chdir(dirname(__DIR__, 1) . '/' . $dir);
}

function server(array $params)
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
            'db_status'          => NULL,
            'redis_status'       => NULL,
            'php_version'        => 'php81',
            'php_cli_version'    => 'php81',
            'database_type'      => 'mysql8',
            'ip_address'         => fake()->ipv4,
            'ssh_port'           => 22,
            'private_ip_address' => fake()->ipv4,
            'local_public_key'   => 'ssh-rsa TEST root@projects',
            'blackfire_status'   => NULL,
            'papertrail_status'  => NULL,
            'revoked'            => false,
            'created_at'         => '2022-08-26T14:07:19.000000Z',
            'is_ready'           => true,
            'tags'               => [],
            'network'            => [],
            'php_version'        => 'php81',
            'php_cli_version'    => 'php81',
        ],
        $params
    );
}

function site(array $params)
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

function forgeUrl(string $path = ''): string
{
    return 'https://forge.laravel.com/api/v1/' . ltrim($path, '/');
}

function deployScript(string $type = 'default')
{
    return file_get_contents(__DIR__ . "/stubs/deploy/{$type}.sh");
}

function envFile(string $type = 'default')
{
    return file_get_contents(__DIR__ . "/stubs/env/{$type}.ini");
}

function fakeForgeRequests(array $server, array $site)
{
    Http::fake([
        forgeUrl('servers') => Http::response([
            'servers' => $servers,
        ]),
        forgeUrl("servers/{$server['id']}/sites") => function (Request $request, array $options) use ($site, $sites) {
            if ($request->method() === 'POST') {
                return [
                    'site' => $site,
                ];
            }

            return [
                'sites' => $sites,
            ];
        },
        forgeUrl("servers/{$server['id']}/php") => Http::response($phpVersions),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}") => Http::response([
            'site' => array_merge($site, ['status' => 'installed']),
        ]),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/") => Http::response([
            'site' => array_merge($site, ['repository_status' => 'installed']),
        ]),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/git") => Http::sequence()->push([
            'site' => array_merge($site, ['repository_status' => 'installed']),
        ]),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/env") => Http::sequence()
            ->push(envFile())
            ->push(envFile()),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/deployment/script") => Http::sequence()
            ->push(deployScript('octane'))
            ->push(deployScript('octane')),
    ]);
}
