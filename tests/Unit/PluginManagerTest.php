<?php

use Bellows\Config;
use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\PluginManager;
use Bellows\ServerProviders\ServerInterface;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class);

it('can identify all available plugins', function () {
    $manager = new PluginManager(
        app(Config::class),
        [
            base_path('tests/FakePlugins'),
        ]
    );

    expect($manager->getAllAvailablePluginNames()->toArray())->toBe([
        'Tests\FakePlugins\FakePlugin1',
        'Tests\FakePlugins\FakePlugin2',
    ]);
});

it('can set the active plugins', function () {
    Http::preventStrayRequests();

    Http::fake([
        'test1' => Http::response(),
        'test2' => Http::response(),
    ]);

    $mock = $this->plugin()->expectsConfirmation('Continue with defaults?', 'yes')->setup();

    $manager = new PluginManager(
        app(Config::class),
        [
            base_path('tests/FakePlugins'),
        ]
    );

    $manager->setPrimaryServer(app(ServerInterface::class));

    $manager->setActive();

    $manager->wrapUp();

    $mock->validate();

    expect(
        $manager->createSiteParams(
            CreateSiteParams::from([
                'domain'       => 'datnewsite.com',
                'project_type' => 'php',
                'directory'    => '/public',
                'isolated'     => true,
                'username'     => 'date_new_site',
                'php_version'  => 'php80',
            ])
        )
    )->toBe([
        [
            'php_version' => '7.4',
        ],
    ]);

    expect(
        $manager->installRepoParams(
            new InstallRepoParams('github', 'bellows/tester', 'main', true)
        )
    )->toBe([
        [
            'branch' => 'devvo',
        ],
    ]);

    expect($manager->environmentVariables())->toBe([
        'TEST_ENV_VAR'   => 'test',
        'TEST_ENV_VAR_2' => 'test2',
    ]);

    expect($manager->daemons()->toArray())->toBe([
        ['daemon1' => 'first daemon'],
    ]);

    expect($manager->workers()->toArray())->toBe([
        ['workers2' => 'second worker'],
    ]);

    expect($manager->jobs()->toArray())->toBe([
        ['job1' => 'first job'],
        ['job2' => 'second job'],
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/test1';
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/test2';
    });
});

it('can enable plugins along the way')->todo();
it('can run the setup method of plugins')->todo();
