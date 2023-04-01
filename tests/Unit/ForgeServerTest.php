<?php

use Bellows\Data\ForgeServer;
use Bellows\Data\PhpVersion;
use Bellows\ServerProviders\Forge\Server;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    // TODO: This feels... wrong.
    // Feels like there's some strange logic bleeding into the test
    Http::macro('forge', fn () => Http::baseUrl('servers/456'));
});

it('can get the php version from the project', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    setPhpVersionForProject('^8.1');

    Http::fake([
        'servers/456/php' => Http::response([
            [
                'id'                  => 29,
                'version'             => 'php74',
                'status'              => 'installed',
                'displayable_version' => 'PHP 7.4',
                'binary_name'         => 'php7.4',
                'used_as_default'     => false,
                'used_on_cli'         => false,
            ],
            [
                'id'                  => 30,
                'version'             => 'php81',
                'status'              => 'installed',
                'displayable_version' => 'PHP 8.1',
                'binary_name'         => 'php8.1',
                'used_as_default'     => false,
                'used_on_cli'         => false,
            ],
        ]),
    ]);

    $mock = $this->plugin()->setup();

    $server = app(
        Server::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
        ]
    );

    $phpVersion = $server->phpVersionFromProject(
        base_path('tests/stubs/plugins/default')
    );

    $mock->validate();

    expect($phpVersion)->toBeInstanceOf(PhpVersion::class);
    expect($phpVersion->name)->toBe('php81');
    expect($phpVersion->binary)->toBe('php8.1');
    expect($phpVersion->display)->toBe('PHP 8.1');
});

it('it will throw an exception when a matching php version cannot be found and installation is rejected', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    setPhpVersionForProject('^8.1');

    Http::fake([
        'servers/456/php' => Http::response([
            [
                'id'                  => 29,
                'version'             => 'php74',
                'status'              => 'installed',
                'displayable_version' => 'PHP 7.4',
                'binary_name'         => 'php7.4',
                'used_as_default'     => false,
                'used_on_cli'         => false,
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsConfirmation(
            'PHP 8.1 is required, but not installed. Install it now?',
            'no'
        )
        ->setup();

    $server = app(
        Server::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
        ]
    );

    $server->phpVersionFromProject(
        base_path('tests/stubs/plugins/default')
    );

    $mock->validate();
})->throws('No PHP version on server found that matches the required version in composer.json');

it('it will offer to install correct php version if it cannot be found', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    setPhpVersionForProject('^8.1');

    // This is strange, but we need to track requests to handle the
    // responses correctly
    $requests = collect();

    Http::fake([
        'servers/456/php' => function (Request $request) use ($requests) {
            if ($request->method() === 'GET') {
                if ($requests->count() === 0) {
                    $requests->push(1);

                    return [
                        [
                            'id'                  => 29,
                            'version'             => 'php74',
                            'status'              => 'installed',
                            'displayable_version' => 'PHP 7.4',
                            'binary_name'         => 'php7.4',
                            'used_as_default'     => false,
                            'used_on_cli'         => false,
                        ],
                    ];
                }

                return [
                    [
                        'id'                  => 29,
                        'version'             => 'php74',
                        'status'              => 'installed',
                        'displayable_version' => 'PHP 7.4',
                        'binary_name'         => 'php7.4',
                        'used_as_default'     => false,
                        'used_on_cli'         => false,
                    ],
                    [
                        'id'                  => 30,
                        'version'             => 'php81',
                        'status'              => 'installed',
                        'displayable_version' => 'PHP 8.1',
                        'binary_name'         => 'php8.1',
                        'used_as_default'     => false,
                        'used_on_cli'         => false,
                    ],
                ];
            }

            return [
                'id'                  => 30,
                'version'             => 'php81',
                'status'              => 'installed',
                'displayable_version' => 'PHP 8.1',
                'binary_name'         => 'php8.1',
                'used_as_default'     => false,
                'used_on_cli'         => false,
            ];
        },
    ]);

    $mock = $this->plugin()
        ->expectsConfirmation(
            'PHP 8.1 is required, but not installed. Install it now?',
            'yes'
        )
        ->setup();

    $server = app(
        Server::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
        ]
    );

    $server->phpVersionFromProject(
        base_path('tests/stubs/plugins/default')
    );

    $mock->validate();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/php'
            && $request->method() === 'POST'
            && $request->data() === [
                'version' => 'php81',
            ];
    });
});
