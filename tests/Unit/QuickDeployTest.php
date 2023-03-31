<?php

use Bellows\Plugins\QuickDeploy;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class)->group('plugin');

beforeEach(function () {
    Http::fake();
});

it('can wrap up', function () {
    $this->plugin()->setup();

    $site = app(SiteInterface::class);

    $plugin = app(QuickDeploy::class);
    $plugin->setSite($site);
    $plugin->wrapUp();

    $site->assertMethodWasCalled('enableQuickDeploy');
});
