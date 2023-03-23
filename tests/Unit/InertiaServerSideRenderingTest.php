<?php

use Bellows\DeployScript;
use Bellows\Plugins\InertiaServerSideRendering;
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
                    'id'   => 1,
                    'name' => 'Test Site',
                ]),
                site([
                    'id'   => 2,
                    'name' => 'Test Site',
                ]),
            ],
        ]),
        'sites/1/env' => Http::response('SSR_PORT=13716'),
        'sites/2/env' => Http::response('SSR_PORT=13717'),
    ]);

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    expect($plugin->environmentVariables())->toBe([
        'SSR_PORT'      => 13718,
        'VITE_SSR_PORT' => '${SSR_PORT}',
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

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    expect($plugin->environmentVariables())->toBe([
        'SSR_PORT'      => 13716,
        'VITE_SSR_PORT' => '${SSR_PORT}',
    ]);
})->group('plugin');

it('can create a daemon', function () {
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

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    expect($plugin->daemons())->toHaveCount(1);
    expect($plugin->daemons()[0]->toArray())->toBe([
        'command'   => 'php81 artisan inertia:start-ssr',
        'user'      => null,
        'directory' => null,
    ]);
})->group('plugin');

it('can update the deploy script', function () {
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

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    expect($deployScript)->toContain('$FORGE_PHP artisan inertia:stop-ssr');
    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
})->group('plugin');
