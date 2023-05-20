<?php

use Bellows\Plugins\SentryPHP;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(Tests\PluginTestCase::class)->group('plugin');

beforeEach(function () {
    installComposerPackage('sentry/sentry-laravel');
});

it('can choose an app from the list', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Sentry project?', 'no')
        ->expectsQuestion('Select a Sentry project', 'bellows-tester')
        ->expectsQuestion('Select a client key', 'Default')
        ->expectsQuestion('Traces Sample Rate', null)
        ->setup();

    $plugin = app(SentryPHP::class);

    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_LARAVEL_DSN' => 'https://1149b2435e91417b86651f61f33d9971@o4505183458361344.ingest.sentry.io/4505188383260672',
    ]);
});

it('can enable performance monitoring', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create Sentry project?', 'no')
        ->expectsQuestion('Select a Sentry project', 'bellows-tester')
        ->expectsQuestion('Select a client key', 'Default')
        ->expectsQuestion('Traces Sample Rate', .3)
        ->setup();

    $plugin = app(SentryPHP::class);

    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_LARAVEL_DSN'        => 'https://1149b2435e91417b86651f61f33d9971@o4505183458361344.ingest.sentry.io/4505188383260672',
        'SENTRY_TRACES_SAMPLE_RATE' => 0.3,
    ]);
});

it('can create a new project', function () {
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

    $plugin = app(SentryPHP::class);

    $plugin->launch();

    $mock->validate();

    Http::assertSent(function (Request $request) {
        return Str::contains($request->url(), '0/teams/joe-codes/joe-codes/projects')
            && $request['platform'] === 'php-laravel'
            && $request['name'] === 'Test App';
    });

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_LARAVEL_DSN' => 'test-dsn',
    ]);
});

it('will use the .env variable if there is one', function () {
    setInEnv('SENTRY_LARAVEL_DSN', 'test-dsn');

    $mock = $this->plugin()->expectsOutputToContain(
        'Using existing Sentry Laravel client key from'
    )->setup();

    $plugin = app(SentryPHP::class);

    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toEqual([
        'SENTRY_LARAVEL_DSN' => 'test-dsn',
    ]);
});
