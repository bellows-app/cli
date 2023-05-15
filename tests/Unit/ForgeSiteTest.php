<?php

use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;
use Bellows\ServerProviders\Forge\Forge;
use Bellows\ServerProviders\Forge\Site;
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

it('can install a repo', function () {
    Http::fake([
        'servers/456/sites/123/' => Http::response([
            'site' => site([
                'id'                => 123,
                'name'              => 'testsite.com',
                'repository_status' => 'installed',
            ]),
        ]),
        'git' => Http::response(),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->installRepo(InstallRepoParams::from([
        'provider'   => 'github',
        'repository' => 'test/repo',
        'branch'     => 'main',
        'composer'   => true,
    ]));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/git'
            && $request->method() === 'POST'
            && $request->data() === [
                'provider'   => 'github',
                'repository' => 'test/repo',
                'branch'     => 'main',
                'composer'   => true,
            ];
    });
});

it('can get the env', function () {
    Http::fake([
        'servers/456/sites/123/env' => Http::response('FOO=bar'),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $result = $server->getEnv();

    expect($result)->toBe('FOO=bar');
});

it('can update the env', function () {
    Http::fake([
        'servers/456/sites/123/env' => Http::response('NEW=bar'),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->updateEnv('NEW=bar');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/env'
            && $request->method() === 'PUT'
            && $request->data() === [
                'content' => 'NEW=bar',
            ];
    });
});

it('can get the deployment script', function () {
    Http::fake([
        'servers/456/sites/123/deployment/script' => Http::response('LET US DEPLOY'),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $result = $server->getDeploymentScript();

    expect($result)->toBe('LET US DEPLOY');
});

it('can update the deployment script', function () {
    Http::fake([
        'servers/456/sites/123/deployment/script' => Http::response('LET US DEPLOY'),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->updateDeploymentScript('OK NOW WE DEPLOYING');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/deployment/script'
            && $request->method() === 'PUT'
            && $request->data() === [
                'content' => 'OK NOW WE DEPLOYING',
            ];
    });
});

it('can create a worker', function () {
    Http::fake([
        'servers/456/sites/123/workers' => Http::response([]),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->createWorker(
        Worker::from([
            'connection'   => 'redis',
            'queue'        => 'default',
            'php_version'  => '7.4',
            'timeout'      => 60,
            'sleep'        => 3,
            'processes'    => 1,
            'stopwaitsecs' => 10,
            'daemon'       => false,
            'force'        => false,
            'tries'        => 0,
        ]),
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/workers'
            && $request->method() === 'POST'
            && $request->data() === [
                'connection'   => 'redis',
                'queue'        => 'default',
                'php_version'  => '7.4',
                'timeout'      => 60,
                'sleep'        => 3,
                'processes'    => 1,
                'stopwaitsecs' => 10,
                'daemon'       => false,
                'force'        => false,
                'tries'        => 0,
            ];
    });
});

it('can create a lets encrypt certificate', function () {
    Http::fake([
        'servers/456/sites/123/certificates'             => Http::response(['certificates' => []]),
        'servers/456/sites/123/certificates/letsencrypt' => Http::response(),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->createSslCertificate(['sslsite.com']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/certificates/letsencrypt'
            && $request->method() === 'POST'
            && $request->data() === [
                'domains' => ['sslsite.com'],
            ];
    });
});

it('will skip certificate creation if a certificate already exists', function () {
    Http::fake([
        'servers/456/sites/123/certificates' => Http::response([
            'certificates' => [
                ['domain' => 'sslsite.com', 'active' => true],
            ],
        ]),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->createSslCertificate(['sslsite.com']);

    Http::assertNotSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/certificates/letsencrypt'
            && $request->method() === 'POST'
            && $request->data() === [
                'domains' => ['sslsite.com'],
            ];
    });
});

it('will skip certificate creation if a certificate already exists with multiple domains', function () {
    Http::fake([
        'servers/456/sites/123/certificates' => Http::response([
            'certificates' => [
                ['domain' => 'sslsite.com,www.sslsite.com', 'active' => true],
            ],
        ]),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->createSslCertificate(['sslsite.com', 'www.sslsite.com']);

    Http::assertNotSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/certificates/letsencrypt'
            && $request->method() === 'POST'
            && $request->data() === [
                'domains' => ['sslsite.com', 'www.sslsite.com'],
            ];
    });
});

it('can enable quick deploy', function () {
    Http::fake([
        'servers/456/sites/123/deployment' => Http::response(),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->enableQuickDeploy();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/deployment'
            && $request->method() === 'POST';
    });
});

it('can enable add security rules', function () {
    Http::fake([
        'servers/456/sites/123/security-rules' => Http::response([]),
    ]);

    $server = app(
        Site::class,
        [
            'server' => ForgeServer::from(server([
                'id'         => 456,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ])),
            'site' => ForgeSite::from(site([
                'id'   => 123,
                'name' => 'testsite.com',
            ])),
        ],
    );

    $server->addSecurityRule(
        SecurityRule::from([
            'name'        => 'restrict',
            'path'        => '/admin',
            'credentials' => collect([
                [
                    'username' => 'admin',
                    'password' => 'secretstuff',
                ],
            ]),
        ]),
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/servers/456/sites/123/security-rules'
            && $request->method() === 'POST'
            && $request->data() === [
                'name'        => 'restrict',
                'path'        => '/admin',
                'credentials' => [
                    [

                        'username' => 'admin',
                        'password' => 'secretstuff',
                    ],
                ],
            ];
    });
});
