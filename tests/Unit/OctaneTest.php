<?php

use Bellows\Data\Daemon;
use Bellows\Plugins\Octane;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::macro(
        'forgeServer',
        fn () => Http::baseUrl('https://forge.laravel.com/api/v1')
            ->acceptJson()
            ->asJson()
    );
});

it('can set the env variable if there are other ports in use', function () {
    Http::fake([
        'sites' => Http::response([
            'sites' => [
                site([
                    'id'           => 1,
                    'name'         => 'Test Site',
                    'project_type' => 'octane',
                ]),
                site([
                    'id'           => 2,
                    'name'         => 'Test Site',
                    'project_type' => 'octane',
                ]),
            ],
        ]),
        'sites/1/env' => Http::response('OCTANE_PORT=8000'),
        'sites/2/env' => Http::response('OCTANE_PORT=8001'),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Which server would you like to use for Octane?', 'swoole')
        ->setup();

    $plugin = app(Octane::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->createSiteParams([]))->toBe([
        'octane_port'  => 8002,
        'project_type' => 'octane',
    ]);

    expect($plugin->environmentVariables())->toBe([
        'OCTANE_SERVER' => 'swoole',
        'OCTANE_PORT'   => 8002,
        'OCTANE_HTTPS'  => 'true',
    ]);

    $daemons = $plugin->daemons();

    expect($daemons)->toHaveCount(1);

    expect($daemons[0])->toBeInstanceOf(Daemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan octane:start --port=8002 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
})->group('plugin');

it('can set the env variable if there are no other ports in use', function () {
    Http::fake([
        'sites' => Http::response([
            'sites' => [
                site([
                    'id'   => 1,
                    'name' => 'Test Site',
                ]),
            ],
        ]),
        'sites/1/env' => Http::response(''),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Which server would you like to use for Octane?', 'swoole')
        ->setup();

    $plugin = app(Octane::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'OCTANE_SERVER' => 'swoole',
        'OCTANE_PORT'   => 8000,
        'OCTANE_HTTPS'  => 'true',
    ]);

    $daemons = $plugin->daemons();

    expect($daemons)->toHaveCount(1);

    expect($daemons[0])->toBeInstanceOf(Daemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan octane:start --port=8000 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
})->group('plugin');
