<?php

use Bellows\Plugins\LaravelWebsockets;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can set the env variable', function () {
    $this->plugin()->setup();

    $plugin = app(LaravelWebsockets::class);
    $plugin->setup();

    expect($plugin->environmentVariables()['BROADCAST_DRIVER'])->toBe('pusher');
});
