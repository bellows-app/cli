<?php

use Bellows\Plugins\LetsEncryptSSL;
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

it('will be disabled if the secure site flag is off', function () {
    overrideProjectConfig([
        'secureSite' => false,
    ]);

    $plugin = app(LetsEncryptSSL::class);
    $plugin->setup();

    expect($plugin->isEnabledByDefault()->enabled)->toBeFalse();
})->setUpBeforeClass();

it('will be enabled if the secure site flag is on', function () {
    overrideProjectConfig([
        'secureSite' => true,
    ]);

    $plugin = app(LetsEncryptSSL::class);
    $plugin->setup();

    expect($plugin->isEnabledByDefault()->enabled)->toBeTrue();
});

it('can set the env variable', function () {
    overrideProjectConfig([
        'domain' => 'bellowstester.com',
    ]);

    $plugin = app(LetsEncryptSSL::class);
    $plugin->setup();

    expect($plugin->environmentVariables())->toBe([
        'APP_URL' => 'https://bellowstester.com',
    ]);
})->group('plugin');

it('can wrap up', function () {
    overrideProjectConfig([
        'domain' => 'bellowstester.com',
    ]);

    Http::fake([
        'certificates/letsencrypt' => Http::response(null, 200),
    ]);

    $plugin = app(LetsEncryptSSL::class);
    $plugin->wrapUp();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/certificates/letsencrypt'
            && $request['domains'] === ['bellowstester.com'];
    });
})->group('plugin');
