<?php

use Bellows\Plugins\BugsnagPHP;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    installComposerPackage('bugsnag/bugsnag-laravel');
});

it('can choose an app from the list', function () {
    installComposerPackage('laravel/framework');

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Bugsnag PHP Project?', 'no')
        ->expectsQuestion('Select a Bugsnag project', 'Forge It Test')
        ->setup();

    $plugin = app(BugsnagPHP::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_API_KEY' => '3bff5a44f63b77591cbd049356e758fb',
    ]);
})->group('plugin');

it('can create a new app', function ($package, $projectType) {
    installComposerPackage($package);

    Http::fake([
        'projects' => Http::response([
            'id'      => '789',
            'name'    => 'Test App',
            'api_key' => 'test-api-key',
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Bugsnag PHP Project?', 'yes')
        ->expectsQuestion('Project name', 'Test App')
        ->setup();

    $plugin = app(BugsnagPHP::class);

    $plugin->setup();

    $mock->validate();

    Http::assertSent(function (Request $request) use ($projectType) {
        return Str::contains($request->url(), 'projects') && $request['type'] === $projectType;
    });

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_API_KEY' => 'test-api-key',
    ]);
})->group('plugin')->with([
    ['laravel/framework', 'laravel'],
    [null, 'php'],
]);

it('will use the .env variable if there is one', function () {
    setInEnv('BUGSNAG_API_KEY', 'test-api-key');

    $mock = $this->plugin()->expectsOutputToContain('Using existing Bugsnag PHP key from')->setup();

    $plugin = app(BugsnagPHP::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_API_KEY' => 'test-api-key',
    ]);
})->group('plugin');
