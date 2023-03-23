<?php

use Bellows\Plugins\Ably;
use Illuminate\Support\Facades\Http;

it('can choose an app from the list', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create new app?', 'no')
        // ->expectsQuestion('Which app do you want to use?', 'Test App')
        ->expectsQuestion('Which app do you want to use?', 'Forge It Test')
        ->expectsQuestion('Which key do you want to use?', 'Subscribe only')
        ->setup();

    $plugin = app(Ably::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'BROADCAST_DRIVER' => 'ably',
        'ABLY_KEY'         => 'pyveqA.ie2olA:8cq-T_FK1kCjwB04wvTbvNqgv0LSIRCRARQ93zDYgyY',
    ]);
})->group('plugin');

it('can create a new app', function () {
    Http::fake([
        'accounts/My7tTw/apps' => Http::response([
            'id'   => '789',
            'name' => 'Test App',
        ]),
        'apps/789/keys' => Http::response([
            [
                'name' => 'Test Key',
                'key'  => 'test-key',
            ],
            [
                'name' => 'Test Key 2',
                'key'  => 'test-key-2',
            ]
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create new app?', 'yes')
        ->expectsQuestion('App name', 'Test App')
        ->expectsQuestion('Which key do you want to use?', 'Test Key')
        ->setup();

    $plugin = app(Ably::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'BROADCAST_DRIVER' => 'ably',
        'ABLY_KEY'         => 'test-key',
    ]);
})->group('plugin');
