<?php

use Bellows\Plugins\QuickDeploy;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

beforeEach(function () {
    Http::fake();
});

it('can wrap up', function () {
    $this->plugin()->mockSite(function (MockInterface $mock) {
        $mock->shouldReceive('enableQuickDeploy')->once();
    })->setup();

    $plugin = app(QuickDeploy::class);
    $plugin->wrapUp();
})->group('plugin');
