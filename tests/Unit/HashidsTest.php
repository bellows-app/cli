<?php

use Bellows\Plugins\Hashids;

uses(Tests\PluginTestCase::class)->group('plugin');

beforeEach(function () {
    $this->plugin()->setup();
});

it('can set the env variable', function () {
    $plugin = app(Hashids::class);
    $plugin->launch();
    expect($plugin->environmentVariables()['HASH_IDS_SALT'])->toBeString();
});
