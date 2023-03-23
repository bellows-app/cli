<?php

use Bellows\Plugins\Hashids;

it('can set the env variable', function () {
    $plugin = app(Hashids::class);
    $plugin->setup();
    expect($plugin->environmentVariables()['HASH_IDS_SALT'])->toBeString();
})->group('plugin');
