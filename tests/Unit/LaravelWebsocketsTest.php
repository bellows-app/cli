<?php

use Bellows\Plugins\LaravelWebsockets;

it('can set the env variable', function () {
    $plugin = app(LaravelWebsockets::class);
    $plugin->setup();
    expect($plugin->environmentVariables()['BROADCAST_DRIVER'])->toBe('pusher');
})->group('plugin');
