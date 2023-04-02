<?php

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http as FacadesHttp;

uses(Tests\PluginTestCase::class);

beforeEach(function () {
    $contents = File::json(base_path('tests/stubs/config/config.json'));

    if (isset($contents['apiCredentials']['example-com'])) {
        unset($contents['apiCredentials']['example-com']);

        File::put(
            base_path('tests/stubs/config/config.json'),
            json_encode($contents, JSON_PRETTY_PRINT),
        );
    }
});

it('can create a default client with new credentials', function ($clientMethod, $validateRequestCallback) {
    FacadesHttp::preventStrayRequests();
    FacadesHttp::fake([
        'test/endpoint' => FacadesHttp::response(),
    ]);

    $mock = $this->plugin()
        ->expectsOutput('✗ No credentials found for: Example API')
        ->expectsOutput('Go ahead, get your token from the url.')
        ->expectsOutput('https://example.com/settings/tokens')
        ->expectsQuestion('Token', 'secretstuff')
        ->expectsQuestion('Account Name (for your own reference)', 'example')
        ->setup();

    app(Http::class)->$clientMethod(
        'https://example.com',
        fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
        new AddApiCredentialsPrompt(
            url: 'https://example.com/settings/tokens',
            credentials: ['token'],
            displayName: 'Example API',
            helpText: 'Go ahead, get your token from the url.',
        ),
        fn (PendingRequest $request) => $request->get('test/endpoint'),
    );

    $mock->validate();

    FacadesHttp::assertSent(function (Request $request) use ($validateRequestCallback) {
        return $request->url() === 'https://example.com/test/endpoint'
            && $request->hasHeader('Authorization', 'Bearer secretstuff')
            && $validateRequestCallback($request);
    });
})->with('http_client_types');

it('will prompt for account selection when there are existing credentials', function ($clientMethod, $validateRequestCallback) {
    $contents = File::json(base_path('tests/stubs/config/config.json'));

    $contents['apiCredentials']['example-com'] = [
        'example' => [
            'token' => 'secretstuff',
        ],
    ];

    File::put(
        base_path('tests/stubs/config/config.json'),
        json_encode($contents, JSON_PRETTY_PRINT),
    );

    FacadesHttp::preventStrayRequests();
    FacadesHttp::fake([
        'test/endpoint' => FacadesHttp::response(),
    ]);

    $mock = $this->plugin()
        ->doesntExpectOutput('Go ahead, get your token from the url.')
        ->doesntExpectOutput('https://example.com/settings/tokens')
        ->expectsChoice('Select account', 'example', ['example', 'Add new account'])
        ->setup();

    app(Http::class)->$clientMethod(
        'https://example.com',
        fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
        new AddApiCredentialsPrompt(
            url: 'https://example.com/settings/tokens',
            credentials: ['token'],
            displayName: 'Example API',
            helpText: 'Go ahead, get your token from the url.',
        ),
        fn (PendingRequest $request) => $request->get('test/endpoint'),
    );

    $mock->validate();

    FacadesHttp::assertSent(function (Request $request) use ($validateRequestCallback) {
        return $request->url() === 'https://example.com/test/endpoint'
            && $request->hasHeader('Authorization', 'Bearer secretstuff')
            && $validateRequestCallback($request);
    });
})->with('http_client_types');

it('will add a new account when add new account is selected', function ($clientMethod, $validateRequestCallback) {
    $contents = File::json(base_path('tests/stubs/config/config.json'));

    $contents['apiCredentials']['example-com'] = [
        'example' => [
            'token' => 'secretstuff',
        ],
    ];

    File::put(
        base_path('tests/stubs/config/config.json'),
        json_encode($contents, JSON_PRETTY_PRINT),
    );

    FacadesHttp::preventStrayRequests();
    FacadesHttp::fake([
        'test/endpoint' => FacadesHttp::response(),
    ]);

    $mock = $this->plugin()
        ->expectsChoice('Select account', 'Add new account', ['example', 'Add new account'])
        ->expectsOutput('Go ahead, get your token from the url.')
        ->expectsOutput('https://example.com/settings/tokens')
        ->expectsQuestion('Token', 'shhh')
        ->expectsQuestion('Account Name (for your own reference)', 'another-example')
        ->setup();

    app(Http::class)->$clientMethod(
        'https://example.com',
        fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
        new AddApiCredentialsPrompt(
            url: 'https://example.com/settings/tokens',
            credentials: ['token'],
            displayName: 'Example API',
            helpText: 'Go ahead, get your token from the url.',
        ),
        fn (PendingRequest $request) => $request->get('test/endpoint'),
    );

    $mock->validate();

    FacadesHttp::assertSent(function (Request $request) use ($validateRequestCallback) {
        return $request->url() === 'https://example.com/test/endpoint'
            && $request->hasHeader('Authorization', 'Bearer shhh')
            && $validateRequestCallback($request);
    });
})->with('http_client_types');

it('can make a request with the default client', function ($clientMethod, $validateRequestCallback) {
    $contents = File::json(base_path('tests/stubs/config/config.json'));

    $contents['apiCredentials']['example-com'] = [
        'example' => [
            'token' => 'secretstuff',
        ],
    ];

    File::put(
        base_path('tests/stubs/config/config.json'),
        json_encode($contents, JSON_PRETTY_PRINT),
    );

    FacadesHttp::preventStrayRequests();
    FacadesHttp::fake([
        'test/endpoint' => FacadesHttp::response(),
        'testaroo' => FacadesHttp::response(),
    ]);

    $mock = $this->plugin()
        ->doesntExpectOutput('Go ahead, get your token from the url.')
        ->doesntExpectOutput('https://example.com/settings/tokens')
        ->expectsChoice('Select account', 'example', ['example', 'Add new account'])
        ->setup();

    $http = app(Http::class);

    $http->$clientMethod(
        'https://example.com',
        fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
        new AddApiCredentialsPrompt(
            url: 'https://example.com/settings/tokens',
            credentials: ['token'],
            displayName: 'Example API',
            helpText: 'Go ahead, get your token from the url.',
        ),
        fn (PendingRequest $request) => $request->get('test/endpoint'),
    );

    $mock->validate();

    $http->extendClient('https://example.com/api', 'extended-client');

    $http->client()->get('testaroo');

    FacadesHttp::assertSent(function (Request $request) use ($validateRequestCallback) {
        return $request->url() === 'https://example.com/testaroo'
            && $request->hasHeader('Authorization', 'Bearer secretstuff')
            && $validateRequestCallback($request);
    });
})->with('http_client_types');


it('can extend an existing client', function ($clientMethod, $validateRequestCallback) {
    $contents = File::json(base_path('tests/stubs/config/config.json'));

    $contents['apiCredentials']['example-com'] = [
        'example' => [
            'token' => 'secretstuff',
        ],
    ];

    File::put(
        base_path('tests/stubs/config/config.json'),
        json_encode($contents, JSON_PRETTY_PRINT),
    );

    FacadesHttp::preventStrayRequests();
    FacadesHttp::fake([
        'test/endpoint' => FacadesHttp::response(),
        'api/testaroo' => FacadesHttp::response(),
    ]);

    $mock = $this->plugin()
        ->doesntExpectOutput('Go ahead, get your token from the url.')
        ->doesntExpectOutput('https://example.com/settings/tokens')
        ->expectsChoice('Select account', 'example', ['example', 'Add new account'])
        ->setup();

    $http = app(Http::class);

    $http->$clientMethod(
        'https://example.com',
        fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
        new AddApiCredentialsPrompt(
            url: 'https://example.com/settings/tokens',
            credentials: ['token'],
            displayName: 'Example API',
            helpText: 'Go ahead, get your token from the url.',
        ),
        fn (PendingRequest $request) => $request->get('test/endpoint'),
    );

    $mock->validate();

    $http->extendClient('https://example.com/api', 'extended-client');

    $http->client('extended-client')->get('testaroo');

    FacadesHttp::assertSent(function (Request $request) use ($validateRequestCallback) {
        return $request->url() === 'https://example.com/api/testaroo'
            && $request->hasHeader('Authorization', 'Bearer secretstuff')
            && $validateRequestCallback($request);
    });
})->with('http_client_types');
