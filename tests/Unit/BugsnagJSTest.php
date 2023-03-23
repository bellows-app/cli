<?php

use Bellows\Config;
use Bellows\Console;
use Bellows\Data\ForgeServer;
use Bellows\Data\ProjectConfig;
use Bellows\Plugins\BugsnagJS;
use Illuminate\Console\BufferedConsoleOutput;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

beforeEach(function () {
    $this->app->bind(
        Config::class,
        fn () => new Config(__DIR__ . '/../stubs/config'),
    );

    $this->app->bind(OutputInterface::class, function () {
        return new BufferedConsoleOutput();
    });

    $this->app->bind(InputInterface::class, function () {
        return new ArgvInput();
    });

    $this->app->bind(ProjectConfig::class, function () {
        return new ProjectConfig(
            isolatedUser: 'tester',
            repositoryUrl: 'bellows/tester',
            repositoryBranch: 'main',
            phpVersion: '8.1',
            phpBinary: 'php81',
            projectDirectory: __DIR__ . '/../stubs/plugins/bugsnag',
            domain: 'bellowstester.com',
            appName: 'Bellows Tester',
            secureSite: true,
        );
    });

    $this->app->bind(ForgeServer::class, function () {
        return ForgeServer::from([
            'id'         => 123,
            'name'       => 'test-server',
            'type'       => 'php',
            'ip_address' => '123.123.123.123',
        ]);
    });

    $this->app->bind(
        Console::class,
        function () {
            $console = new Console();

            $console->setOutput(app(OutputStyle::class));

            return $console;
        }
    );

    Http::preventStrayRequests();
});

it('can choose an app from the list', function () {
    Http::fake([
        'user/organizations?per_page=1' => Http::response([
            [
                'id' => '123',
            ],
        ]),
        'user/organizations' => Http::response([
            [
                'id' => '123',
            ],
        ]),
        'organizations/123/projects?per_page=100' => Http::response([
            [
                'api_key' => '456',
                'name'    => 'Test App',
                'type'    => 'js',
            ],
            [
                'api_key' => '789',
                'name'    => 'Test App 2',
                'type'    => 'js',
            ],
        ]),
    ]);

    $plugin = app(BugsnagJS::class);

    $plugin->setup();

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_JS_API_KEY'      => '456',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
})->group('plugin');

it('can create a new app', function () {
    Http::fake([
        'user/organizations?per_page=1' => Http::response([
            [
                'id' => '123',
            ],
        ]),
        'user/organizations' => Http::response([
            [
                'id' => '123',
            ],
        ]),
        'organizations/123/projects' => Http::response([
            'api_key' => '789',
            'name'    => 'Test App 2',
        ]),
    ]);

    $plugin = app(BugsnagJS::class);

    $plugin->setup();

    expect($plugin->environmentVariables())->toEqual([
        'BUGSNAG_JS_API_KEY'      => '789',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
})->group('plugin');
