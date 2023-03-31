<?php

use Bellows\Plugins\FathomAnalytics;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can choose an app from the list', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create new Fathom Analytics site?', 'no')
        ->expectsQuestion('Choose a site', 'Forge It Test')
        ->setup();

    $plugin = app(FathomAnalytics::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'FATHOM_SITE_ID' => 'SKPAQITB',
    ]);
});

it('can create a new app', function () {
    Http::fake([
        'sites' => Http::response([
            'id'      => '789',
            'name'    => 'Test App',
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create new Fathom Analytics site?', 'yes')
        ->expectsQuestion('Enter your site name', 'Test App')
        ->setup();

    $plugin = app(FathomAnalytics::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'FATHOM_SITE_ID' => '789',
    ]);
});
