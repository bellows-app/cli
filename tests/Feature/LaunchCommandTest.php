<?php

use Bellows\Config;
use Bellows\Console;
use Bellows\PluginManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Http::preventStrayRequests();
    Process::preventStrayProcesses();

    $this->plugin()->setup();

    Process::fake();

    // Process::fake([
    //     'pwd' => Process::result(dirname(__DIR__, 1) . '/test-app'),
    // ]);
});

// Ensure that when no plugins are active it can just create a site
// Ensure that when something comes back from plugins, it is integrated into the data sent to Forge and that the prompts are correct
// Create a fake plugin called BusyBody and it returns all possible things that can be set, make sure that it is integrated into the data sent to Forge and that the prompts are correct

it('launches a simple site', function () {
    $site = sites()[0];
    $server = servers()[0];

    Http::fake([
        forgeUrl('user')    => Http::response(null, 200),
        forgeUrl('servers') => Http::response([
            'servers' => servers(),
        ]),
        forgeUrl("servers/{$server['id']}/sites") => Http::sequence()
            ->push([
                'sites' => sites(), // Check for existing domain
            ])
            ->push([
                'site' => $site, // Add site
            ]),
        forgeUrl("servers/{$server['id']}/php")                 => Http::response(forgePhpVersions()),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}") => Http::response([
            'site' => array_merge($site, ['status' => 'installed']),
        ]),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/") => Http::sequence()->push([
            'site' => array_merge($site, ['repository_status' => 'installing']),
        ])->push([
            'site' => array_merge($site, ['repository_status' => 'installed']),
        ]),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/git") => Http::sequence()->push([
            'site' => array_merge($site, ['repository_status' => 'installing']),
        ])->push([
            'site' => array_merge($site, ['repository_status' => 'installed']),
        ]),
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/env") => Http::sequence()
            ->push(envFile()) // Get
            ->push(envFile()), // Update
        forgeUrl("servers/{$server['id']}/sites/{$site['id']}/deployment/script") => Http::sequence()
            ->push(deployScript('octane')) // Get
            ->push(deployScript('octane')), // Update
    ]);

    cdTo('stubs/test-app');

    app()->instance(PluginManager::class, new PluginManager(
        app(Console::class),
        app(Config::class),
        [__DIR__]
    ));

    $this->artisan('launch')
        ->expectsQuestion('Which server would you like to use?', 'testserver')
        ->expectsQuestion('App Name', 'Test Project')
        ->expectsQuestion('Domain', 'testproject.com')
        ->expectsQuestion('Isolated User', 'test_project')
        ->expectsQuestion('Repository', 'joetannenbaum/test-project')
        ->expectsQuestion('Repository Branch', 'main')
        ->expectsConfirmation('Would you like to add any of them? They will be added with their existing values.', 'no')
        ->expectsConfirmation('Open site in Forge?', 'no')
        ->assertExitCode(0);
});

it('will exit if there is no .env file', function () {
    Http::fake([
        forgeUrl('user')    => Http::response(null, 200),
    ]);

    cdTo('stubs/empty-test-app');

    app()->instance(PluginManager::class, new PluginManager(
        app(Console::class),
        app(Config::class),
        [__DIR__]
    ));

    $this->artisan('launch')
        ->expectsOutputToContain('No .env file found! Are you in the correct directory?')
        ->assertExitCode(0);
});

it('will exit if domain already exists on server', function () {
    $site = site([
        'name' => 'testsite.com',
    ]);
    $server = servers()[0];

    Http::fake([
        forgeUrl('user')    => Http::response(null, 200),
        forgeUrl('servers') => Http::response([
            'servers' => servers(),
        ]),
        forgeUrl("servers/{$server['id']}/sites") => Http::sequence()
            ->push([
                'sites' => collect(sites())->concat($site)->toArray(), // Check for existing domain
            ]),
    ]);

    cdTo('stubs/test-app');

    app()->instance(PluginManager::class, new PluginManager(
        app(Console::class),
        app(Config::class),
        [__DIR__]
    ));

    $this->artisan('launch')
        ->expectsQuestion('Which server would you like to use?', 'testserver')
        ->expectsQuestion('App Name', 'Test Project')
        ->expectsQuestion('Domain', 'testsite.com')
        ->expectsConfirmation('View existing site in Forge?', 'no')
        ->assertExitCode(0);
});
