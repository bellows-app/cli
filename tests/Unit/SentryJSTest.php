<?php

use Bellows\Plugins\SentryJS;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can choose an app from the list', function ($package, $dsn) {
    installNpmPackage($package);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Sentry project?', 'no')
        ->expectsQuestion('Select a Sentry project', 'bellows-tester')
        ->expectsQuestion('Select a client key', 'Default')
        ->expectsQuestion('Traces Sample Rate', null)
        ->setup();

    $plugin = app(SentryJS::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_JS_DSN'      => $dsn,
        'VITE_SENTRY_JS_DSN' => '${SENTRY_JS_DSN}',
    ]);
})->with([
    ['@sentry/vue', 'https://4dbd954238214443a51b2617f1a77ec2@o4505183458361344.ingest.sentry.io/4505188525473792'],
    ['@sentry/react', 'https://fb9e814b5a614be3b1b42712d13afaf6@o4505183458361344.ingest.sentry.io/4505188524228608'],
]);

it('can enable performance monitoring', function () {
    installNpmPackage('@sentry/vue');

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Sentry project?', 'no')
        ->expectsQuestion('Select a Sentry project', 'bellows-tester')
        ->expectsQuestion('Select a client key', 'Default')
        ->expectsQuestion('Traces Sample Rate', .3)
        ->setup();

    $plugin = app(SentryJS::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_JS_DSN'                     => 'https://4dbd954238214443a51b2617f1a77ec2@o4505183458361344.ingest.sentry.io/4505188525473792',
        'VITE_SENTRY_JS_DSN'                => '${SENTRY_JS_DSN}',
        'SENTRY_JS_TRACES_SAMPLE_RATE'      => .3,
        'VITE_SENTRY_JS_TRACES_SAMPLE_RATE' => '${SENTRY_JS_TRACES_SAMPLE_RATE}',
    ]);
});

it('can create a new project', function ($package, $projectType) {
    installNpmPackage($package);

    Http::fake([
        '0/teams/joe-codes/joe-codes/projects/' => Http::response([
            'slug' => 'test-app',
            'dsn'  => [
                'public' => 'test-dsn',
            ],
        ]),
        '0/projects/joe-codes/test-app/keys/' => Http::response([
            [
                'name' => 'Default',
                'dsn'  => [
                    'public' => 'test-dsn',
                ],
            ],
        ]),
    ]);

    overrideProjectConfig([
        'name' => 'Test App',
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Sentry project?', 'yes')
        ->expectsQuestion('Select a team', 'Joe Codes')
        ->expectsQuestion('Project name', 'Test App')
        ->expectsQuestion('Select a client key', 'Default')
        ->expectsQuestion('Traces Sample Rate', null)
        ->setup();

    $plugin = app(SentryJS::class);

    $plugin->setup();

    $mock->validate();

    Http::assertSent(function (Request $request) use ($projectType) {
        return Str::contains($request->url(), '0/teams/joe-codes/joe-codes/projects')
            && $request['platform'] === $projectType
            && $request['name'] === 'Test App';
    });

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_JS_DSN'      => 'test-dsn',
        'VITE_SENTRY_JS_DSN' => '${SENTRY_JS_DSN}',
    ]);
})->with([
    ['@sentry/vue', 'javascript-vue'],
    ['@sentry/react', 'javascript-react'],
]);

it('will use the .env variable if there is one', function () {
    setInEnv('SENTRY_JS_DSN', 'test-dsn');

    $mock = $this->plugin()->expectsOutputToContain(
        'Using existing Sentry JS client key from'
    )->setup();

    $plugin = app(SentryJS::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_JS_DSN'      => 'test-dsn',
        'VITE_SENTRY_JS_DSN' => '${SENTRY_JS_DSN}',
    ]);
});
