<?php

use Bellows\Plugins\LetsEncryptSSL;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class)->group('plugin');

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
});

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
});

it('can wrap up', function () {
    overrideProjectConfig([
        'domain' => 'bellowstester.com',
    ]);

    $this->plugin()->setup();

    $site = app(SiteInterface::class);

    $plugin = app(LetsEncryptSSL::class);
    $plugin->setSite($site);
    $plugin->wrapUp();

    $site->assertMethodWasCalled('createSslCertificate', ['bellowstester.com', 'www.bellowstester.com']);
});
