<?php

use Bellows\Plugins\BugsnagJS;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(Tests\PluginTestCase::class)->group('plugin');

beforeEach(function () {
    installNpmPackage('@bugsnag/js');
});

it('can choose an app from the list', function () {
    installNpmPackage('vue');

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Bugsnag JS project?', 'no')
        ->expectsQuestion('Select a Bugsnag project', 'Forge It Test')
        ->setup();

    $plugin = app(BugsnagJS::class);

    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_JS_API_KEY'      => '51d176679b06ba1dfdce09077cbc696c',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
});

it('can create a new app', function ($package, $projectType) {
    installNpmPackage($package);

    Http::fake([
        'projects' => Http::response([
            'id'      => '789',
            'name'    => 'Test App',
            'api_key' => 'test-api-key',
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Bugsnag JS project?', 'yes')
        ->expectsQuestion('Project name', 'Test App')
        ->setup();

    $plugin = app(BugsnagJS::class);

    $plugin->launch();

    $mock->validate();

    Http::assertSent(function (Request $request) use ($projectType) {
        return Str::contains($request->url(), 'projects') && $request['type'] === $projectType;
    });

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_JS_API_KEY'      => 'test-api-key',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
})->group('plugin')->with([
    ['vue', 'vue'],
    ['react', 'react'],
    [null, 'js'],
]);

it('will use the .env variable if there is one', function () {
    setInEnv('BUGSNAG_JS_API_KEY', 'test-api-key');

    $mock = $this->plugin()->expectsOutputToContain('Using existing Bugsnag JS key from')->setup();

    $plugin = app(BugsnagJS::class);

    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_JS_API_KEY'      => 'test-api-key',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
});
