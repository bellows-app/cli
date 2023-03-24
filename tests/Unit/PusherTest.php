<?php

use Bellows\Plugins\Pusher;

it('can select a pusher app from the list', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Which app do you want to use?', 'dm2-staging')
        ->setup();

    $plugin = app(Pusher::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'BROADCAST_DRIVER'   => 'pusher',
        'PUSHER_APP_ID'      => 1551260,
        'PUSHER_APP_KEY'     => '6534946fa42b08bfb704',
        'PUSHER_APP_SECRET'  => '382ae76b47a6a1403066',
        'PUSHER_APP_CLUSTER' => 'us2',
    ]);
})->group('plugin');

it('can refresh the list', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Which app do you want to use?', 'Refresh App List')
        ->expectsQuestion('Which app do you want to use?', 'dm2-staging')
        ->setup();

    $plugin = app(Pusher::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'BROADCAST_DRIVER'   => 'pusher',
        'PUSHER_APP_ID'      => 1551260,
        'PUSHER_APP_KEY'     => '6534946fa42b08bfb704',
        'PUSHER_APP_SECRET'  => '382ae76b47a6a1403066',
        'PUSHER_APP_CLUSTER' => 'us2',
    ]);
})->group('plugin');
