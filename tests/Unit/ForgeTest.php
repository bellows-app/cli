<?php

use Bellows\ServerProviders\Forge\Forge;
use Bellows\ServerProviders\ServerInterface;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('can get a server from a list', function () {
    Http::fake([
        'user'    => Http::response(),
        'servers' => Http::response([
            'servers' => [
                server([
                    'id'   => 1,
                    'name' => 'test-server',
                ]),
                server([
                    'id'   => 2,
                    'name' => 'test-server-2',
                ]),
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Which server would you like to use?', 'test-server-2')
        ->setup();

    $forge = app(Forge::class);

    $forge->setCredentials();

    $server = $forge->getServer();

    $mock->validate();

    expect($server)->toBeInstanceOf(ServerInterface::class);
    expect($server->id)->toBe(2);
});

it('will auto select the server when there is only one', function () {
    Http::fake([
        'user'    => Http::response(),
        'servers' => Http::response([
            'servers' => [
                server([
                    'id'   => 1,
                    'name' => 'test-server',
                ]),
            ],
        ]),
    ]);

    $mock = $this->plugin()->setup();

    $forge = app(Forge::class);

    $forge->setCredentials();

    $server = $forge->getServer();

    $mock->validate();

    expect($server)->toBeInstanceOf(ServerInterface::class);
    expect($server->id)->toBe(1);
});

it('can set credentials')->todo();
it('can set new credentials when the old ones are invalid')->todo();
