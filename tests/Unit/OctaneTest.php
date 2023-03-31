<?php

use Bellows\Data\ForgeSite;
use Bellows\Plugins\Octane;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

beforeEach(function () {
    Http::fake();
});

it('can set the env variable if there are other ports in use', function () {
    $mock = $this->plugin()
        ->mockServer(function (MockInterface $mock) {
            $mock->shouldReceive('getSites')->once()->andReturn(
                collect([
                    ForgeSite::from(site([
                        'id'           => 1,
                        'name'         => 'Test Site',
                        'project_type' => 'octane',
                    ])),
                    ForgeSite::from(site([
                        'id'           => 2,
                        'name'         => 'Test Site',
                        'project_type' => 'octane',
                    ])),
                ])
            );

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(1)
                ->andReturn('OCTANE_PORT=8000');

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(2)
                ->andReturn('OCTANE_PORT=8001');
        })
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

    expect($daemons[0])->toBeInstanceOf(\Bellows\Data\Daemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan octane:start --port=8002 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
})->group('plugin');

it('can set the env variable if there are no other ports in use', function () {
    $mock = $this->plugin()
        ->mockServer(function (MockInterface $mock) {
            $mock->shouldReceive('getSites')->once()->andReturn(
                collect([
                    ForgeSite::from(site([
                        'id'           => 1,
                        'name'         => 'Test Site',
                        'project_type' => 'octane',
                    ])),
                ])
            );

            $mock->shouldReceive('getSiteEnv')
                ->once()
                ->with(1)
                ->andReturn('');
        })
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

    expect($daemons[0])->toBeInstanceOf(\Bellows\Data\Daemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan octane:start --port=8000 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
})->group('plugin');
