<?php

use Bellows\Plugins\Hashids;

beforeEach(function () {
    $this->plugin()->setup();
});

it('can set the env variable', function () {
    $plugin = app(Hashids::class);
    $plugin->setup();
    expect($plugin->environmentVariables()['HASH_IDS_SALT'])->toBeString();
})->group('plugin');
