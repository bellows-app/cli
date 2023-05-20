<?php

use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\DeployScript;
use Bellows\Plugins\InertiaServerSideRendering;
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
                    'id'   => 1,
                    'name' => 'Test Site',
                ])),
                ForgeSite::from(
                    site([
                        'id'   => 2,
                        'name' => 'Test Site',
                    ])
                ),
            ]);
        }

        public function getSiteEnv(int $siteId): string
        {
            return $siteId === 1 ? 'SSR_PORT=13716' : 'SSR_PORT=13717';
        }
    });

    $this->plugin()->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setServer(app(ServerInterface::class));
    $plugin->launch();

    expect($plugin->environmentVariables())->toBe([
        'SSR_PORT'      => 13718,
        'VITE_SSR_PORT' => '${SSR_PORT}',
    ]);
});

it('can set the env variable if there are no other ports in use', function () {
    $this->app->bind(ServerInterface::class, fn () => new class(app(ForgeServer::class)) extends FakeServer
    {
        public function getSites(): Collection
        {
            return collect([
                ForgeSite::from(site([
                    'id'   => 1,
                    'name' => 'Test Site',
                ])),
            ]);
        }

        public function getSiteEnv(int $siteId): string
        {
            return '';
        }
    });

    $this->plugin()->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setServer(app(ServerInterface::class));
    $plugin->launch();

    expect($plugin->environmentVariables())->toBe([
        'SSR_PORT'      => 13716,
        'VITE_SSR_PORT' => '${SSR_PORT}',
    ]);
});

it('can create a daemon', function () {
    $this->app->bind(ServerInterface::class, fn () => new class(app(ForgeServer::class)) extends FakeServer
    {
        public function getSites(): Collection
        {
            return collect([
                ForgeSite::from(site([
                    'id'   => 1,
                    'name' => 'Test Site',
                ])),
            ]);
        }

        public function getSiteEnv(int $siteId): string
        {
            return '';
        }
    });

    $this->plugin()->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setServer(app(ServerInterface::class));
    $plugin->launch();

    $daemons = $plugin->daemons();

    expect($daemons)->toHaveCount(1);

    expect($daemons[0])->toBeInstanceOf(\Bellows\Data\PluginDaemon::class);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'php81 artisan inertia:start-ssr',
        'user'      => null,
        'directory' => null,
    ]);
});

it('can update the deploy script', function () {
    $this->app->bind(ServerInterface::class, fn () => new class(app(ForgeServer::class)) extends FakeServer
    {
        public function getSites(): Collection
        {
            return collect([
                ForgeSite::from(site([
                    'id'   => 1,
                    'name' => 'Test Site',
                ])),
            ]);
        }

        public function getSiteEnv(int $siteId): string
        {
            return '';
        }
    });

    $this->plugin()->setup();

    $plugin = app(InertiaServerSideRendering::class);
    $plugin->setServer(app(ServerInterface::class));
    $plugin->launch();

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    expect($deployScript)->toContain('$FORGE_PHP artisan inertia:stop-ssr');
    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
});
