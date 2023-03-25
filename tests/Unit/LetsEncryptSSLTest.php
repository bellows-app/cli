<?php

use Bellows\Plugins\LetsEncryptSSL;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

beforeEach(function () {
    Http::fake();
});

it('will be disabled if the secure site flag is off', function () {
    overrideProjectConfig([
        'secureSite' => false,
    ]);

    $this->plugin()->setup();

    $plugin = app(LetsEncryptSSL::class);
    $plugin->setup();

    expect($plugin->isEnabledByDefault()->enabled)->toBeFalse();
})->setUpBeforeClass();

it('will be enabled if the secure site flag is on', function () {
    overrideProjectConfig([
        'secureSite' => true,
    ]);

    $this->plugin()->setup();

    $plugin = app(LetsEncryptSSL::class);
    $plugin->setup();

    expect($plugin->isEnabledByDefault()->enabled)->toBeTrue();
});

it('can set the env variable', function () {
    overrideProjectConfig([
        'domain' => 'bellowstester.com',
    ]);

    $this->plugin()->setup();

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

    $this->plugin()->mockSite(function (MockInterface $mock) {
        $mock->shouldReceive('createSslCertificate')->with('bellowstester.com')->once();
    })->setup();

    $plugin = app(LetsEncryptSSL::class);
    $plugin->wrapUp();
})->group('plugin');
