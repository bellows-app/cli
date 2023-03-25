<?php

use Bellows\Data\Daemon;
use Bellows\Data\ForgeSite;
use Bellows\DeployScript;
use Bellows\Plugins\InertiaServerSideRendering;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

beforeEach(function () {
    Http::fake();
});

it('can set the env variable if there are other ports in use', function () {
    $this->plugin()
        ->mockServer(function (MockInterface $mock) {
            $mock->shouldReceive('getSites')->once()->andReturn(
                collect([
                    ForgeSite::from(site([
                        'id'   => 1,
                        'name' => 'Test Site',
                    ])),
                    ForgeSite::from(site([
                        'id'   => 2,
                        'name' => 'Test Site',
                    ])),
                ])
            );

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(1)
                ->andReturn('SSR_PORT=13716');

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(2)
                ->andReturn('SSR_PORT=13717');
        })
        ->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    expect($plugin->environmentVariables())->toBe([
        'SSR_PORT'      => 13718,
        'VITE_SSR_PORT' => '${SSR_PORT}',
    ]);
})->group('plugin');

it('can set the env variable if there are no other ports in use', function () {
    $this->plugin()
        ->mockServer(function (MockInterface $mock) {
            $mock->shouldReceive('getSites')->once()->andReturn(
                collect([
                    ForgeSite::from(site([
                        'id'   => 1,
                        'name' => 'Test Site',
                    ])),
                ])
            );

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(1)
                ->andReturn('');
        })
        ->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    expect($plugin->environmentVariables())->toBe([
        'SSR_PORT'      => 13716,
        'VITE_SSR_PORT' => '${SSR_PORT}',
    ]);
})->group('plugin');

it('can create a daemon', function () {
    $this->plugin()
        ->mockServer(function (MockInterface $mock) {
            $mock->shouldReceive('getSites')->once()->andReturn(
                collect([
                    ForgeSite::from(site([
                        'id'   => 1,
                        'name' => 'Test Site',
                    ])),
                ])
            );

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(1)
                ->andReturn('');
        })
        ->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    $daemons = $plugin->daemons();

    expect($daemons)->toHaveCount(1);

    expect($daemons[0])->toBeInstanceOf(Daemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan inertia:start-ssr',
        'user'      => null,
        'directory' => null,
    ]);
})->group('plugin');

it('can update the deploy script', function () {
    $this->plugin()
        ->mockServer(function (MockInterface $mock) {
            $mock->shouldReceive('getSites')->once()->andReturn(
                collect([
                    ForgeSite::from(site([
                        'id'   => 1,
                        'name' => 'Test Site',
                    ])),
                ])
            );

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(1)
                ->andReturn('');
        })
        ->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setup();

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    expect($deployScript)->toContain('$FORGE_PHP artisan inertia:stop-ssr');
    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
})->group('plugin');
