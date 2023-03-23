<?php

use Bellows\Plugins\QuickDeploy;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::macro(
        'forgeSite',
        fn () => Http::baseUrl('https://forge.laravel.com/api/v1')
            ->acceptJson()
            ->asJson()
    );
});

it('can wrap up', function () {
    Http::fake([
        'deployment' => Http::response(null, 200),
    ]);

    $plugin = app(QuickDeploy::class);
    $plugin->wrapUp();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/deployment';
    });
})->group('plugin');
