<?php

use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Plugins\Octane;
use Bellows\ServerProviders\ServerInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeServer;

uses(Tests\PluginTestCase::class)->group('plugin');

beforeEach(function () {
    Http::fake();
});

it('can set the env variable if there are other ports in use', function () {
    $this->app->bind(ServerInterface::class, fn () => new class(app(ForgeServer::class)) extends FakeServer
    {
        public function getSites(): Collection
        {
            return collect([
                ForgeSite::from(site([
                    'id'           => 1,
                    'name'         => 'Test Site',
                    'project_type' => 'octane',
                ])),
                ForgeSite::from(
                    site([
                        'id'           => 2,
                        'name'         => 'Test Site',
                        'project_type' => 'octane',
                    ])
                ),
            ]);
        }

        public function getSiteEnv(int $siteId): string
        {
            return $siteId === 1 ? 'OCTANE_PORT=8000' : 'OCTANE_PORT=8001';
        }
    });

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

    expect($daemons[0])->toBeInstanceOf(\Bellows\Data\Daemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan octane:start --port=8002 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
});

it('can set the env variable if there are no other ports in use', function () {
    $this->app->bind(ServerInterface::class, fn () => new class(app(ForgeServer::class)) extends FakeServer
    {
        public function getSites(): Collection
        {
            return collect([
                ForgeSite::from(site([
                    'id'           => 1,
                    'name'         => 'Test Site',
                    'project_type' => 'octane',
                ])),
            ]);
        }

        public function getSiteEnv(int $siteId): string
        {
            return '';
        }
    });

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

    expect($daemons[0])->toBeInstanceOf(\Bellows\Data\Daemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan octane:start --port=8000 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
});
