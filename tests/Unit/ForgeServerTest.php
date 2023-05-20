<?php

use Bellows\Data\CreateSiteParams;
use Bellows\Data\Daemon;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Bellows\ServerProviders\Forge\Forge;
use Bellows\ServerProviders\Forge\Server;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    // Token validation request
    Http::fake([
        'user' => Http::response(),
    ]);
    app(Forge::class)->setCredentials();
});

it('can get the php version from the project', function () {
    overrideProjectConfig([
        'directory' => base_path('tests/stubs/plugins/default'),
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

    $phpVersion = $server->determinePhpVersionFromProject();

    $mock->validate();

    expect($phpVersion)->toBeInstanceOf(PhpVersion::class);
    expect($phpVersion->version)->toBe('php81');
    expect($phpVersion->binary)->toBe('php8.1');
    expect($phpVersion->display)->toBe('PHP 8.1');
});

it('it will throw an exception when a matching php version cannot be found and installation is rejected', function () {
    overrideProjectConfig([
        'directory' => base_path('tests/stubs/plugins/default'),
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

    $server->determinePhpVersionFromProject();

    $mock->validate();
})->throws('No PHP version on server found that matches the required version in composer.json');

it('it will offer to install correct php version if it cannot be found', function () {
    overrideProjectConfig([
        'directory' => base_path('tests/stubs/plugins/default'),
    ]);

    setPhpVersionForProject('^8.1');

    Http::fake([
        'servers/456/php' => Http::sequence([
            [
                [
                    'id'                  => 29,
                    'version'             => 'php74',
                    'status'              => 'installed',
                    'displayable_version' => 'PHP 7.4',
                    'binary_name'         => 'php7.4',
                    'used_as_default'     => false,
                    'used_on_cli'         => false,
                ],
            ],
            [
                'id'                  => 30,
                'version'             => 'php81',
                'status'              => 'installing',
                'displayable_version' => 'PHP 8.1',
                'binary_name'         => 'php8.1',
                'used_as_default'     => false,
                'used_on_cli'         => false,
            ],
            [
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
            ],
        ]),
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

    $server->determinePhpVersionFromProject();

    $mock->validate();

    Http::assertSent(fn (Request $request) => matchesRequest(
        $request,
        'https://forge.laravel.com/api/v1/servers/456/php',
        'post',
        ['version' => 'php81'],
    ));
});

it('can retrieve a list of sites', function () {
    Http::fake([
        'sites' => Http::response([
            'sites' => [
                site([
                    'id'   => 1,
                    'name' => 'testsite.com',
                ]),
                site([
                    'id'   => 2,
                    'name' => 'anothersite.com',
                ]),
            ],
        ]),
    ]);

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

    $result = $server->getSites();

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->count())->toBe(2);

    expect($result->first())->toBeInstanceOf(ForgeSite::class);
    expect($result->first()->id)->toBe(1);
    expect($result->first()->name)->toBe('testsite.com');

    expect($result->last())->toBeInstanceOf(ForgeSite::class);
    expect($result->last()->id)->toBe(2);
    expect($result->last()->name)->toBe('anothersite.com');
});

it('can get a site by its domain', function () {
    Http::fake([
        'sites' => Http::response([
            'sites' => [
                site([
                    'id'   => 1,
                    'name' => 'testsite.com',
                ]),
                site([
                    'id'   => 2,
                    'name' => 'anothersite.com',
                ]),
            ],
        ]),
    ]);

    $this->plugin()->setup();

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

    $result = $server->getSiteByDomain('testsite.com');

    expect($result)->toBeInstanceOf(ForgeSite::class);
    expect($result->id)->toBe(1);
    expect($result->name)->toBe('testsite.com');
});

it('will return null if a site does not exist on the server', function () {
    Http::fake([
        'sites' => Http::response([
            'sites' => [
                site([
                    'id'   => 1,
                    'name' => 'testsite.com',
                ]),
                site([
                    'id'   => 2,
                    'name' => 'anothersite.com',
                ]),
            ],
        ]),
    ]);

    $this->plugin()->setup();

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

    $result = $server->getSiteByDomain('nada.com');

    expect($result)->toBeNull();
});

it('can create a site', function () {
    Http::fake([
        'sites' => Http::response(
            [
                'site' => site([
                    'id'   => 123,
                    'name' => 'datnewsite.com',
                ]),
            ]
        ),
        'sites/123' => Http::response(
            [
                'site' => site([
                    'id'     => 123,
                    'name'   => 'datnewsite.com',
                    'status' => 'installed',
                ]),
            ]
        ),
    ]);

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

    $result = $server->createSite(CreateSiteParams::from([
        'domain'       => 'datnewsite.com',
        'project_type' => 'php',
        'directory'    => '/public',
        'isolated'     => true,
        'username'     => 'date_new_site',
        'php_version'  => 'php80',
    ]));

    expect($result)->toBeInstanceOf(SiteInterface::class);
    expect($result->id)->toBe(123);
    expect($result->name)->toBe('datnewsite.com');
});

it('can create a daemon', function () {
    Http::fake(['*' => Http::response([])]);

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

    $result = $server->createDaemon(Daemon::from([
        'command'   => 'php artisan queue:work',
        'user'      => 'forge',
        'directory' => '/home/forge/datnewsite.com',
    ]));

    expect($result)->toBeArray();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/daemons'
            && $request->method() === 'POST'
            && $request->data() === [
                'command'   => 'php artisan queue:work',
                'user'      => 'forge',
                'directory' => '/home/forge/datnewsite.com',
            ];
    });
});

it('can create a job', function () {
    Http::fake(['*' => Http::response([])]);

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

    $result = $server->createJob(Job::from([
        'command'   => 'php artisan queue:work',
        'frequency' => 'hourly',
        'user'      => 'forge',
    ]));

    expect($result)->toBeArray();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/jobs'
            && $request->method() === 'POST'
            && $request->data() === [
                'command'   => 'php artisan queue:work',
                'frequency' => 'hourly',
                'user'      => 'forge',
            ];
    });
});

it('can get a site env', function () {
    Http::fake(['sites/123/env' => Http::response('FOO=bar')]);

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

    $result = $server->getSiteEnv(123);

    expect($result)->toBe('FOO=bar');
});
