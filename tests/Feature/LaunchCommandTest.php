<?php

use Bellows\ServerProviders\Forge\Forge;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Http::fake();
    Process::fake();

    Process::preventStrayProcesses();

    $this->app->bind(
        \Bellows\PluginManagerInterface::class,
        fn () => new \Tests\Fakes\FakePluginManager,
    );
    $this->app->bind(
        \Bellows\ServerProviders\ServerProviderInterface::class,
        fn () => new \Tests\Fakes\FakeForge,
    );
    $this->app->bind(
        \Bellows\ServerProviders\ServerInterface::class,
        fn () => app(\Tests\Fakes\FakeServer::class),
    );
    $this->app->bind(
        \Bellows\ServerProviders\SiteInterface::class,
        fn () => app(\Tests\Fakes\FakeSite::class),
    );
});

// Ensure that when something comes back from plugins, it is integrated into the data sent to Forge and that the prompts are correct
// Create a fake plugin called BusyBody and it returns all possible things that can be set, make sure that it is integrated into the data sent to Forge and that the prompts are correct

it('launches a simple site', function () {
    cdTo('stubs/test-app');

    $this->artisan('launch')
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
    cdTo('stubs/empty-test-app');

    $this->artisan('launch')
        ->expectsOutputToContain('No .env file found! Are you in the correct directory?')
        ->assertExitCode(0);
});

it('will exit if domain already exists on server', function () {
    $this->app->bind(
        \Bellows\ServerProviders\ServerInterface::class,
        fn () => new class(app(\Bellows\Data\ForgeServer::class)) extends \Tests\Fakes\FakeServer
        {
            public function getSiteByDomain(string $domain): ?Bellows\Data\ForgeSite
            {
                return \Bellows\Data\ForgeSite::from(site([
                    'id'   => 123,
                    'name' => 'testsite.com',
                ]));
            }
        }
    );

    cdTo('stubs/test-app');

    $this->artisan('launch')
        ->expectsQuestion('App Name', 'Test Project')
        ->expectsQuestion('Domain', 'testsite.com')
        ->expectsConfirmation('View existing site in Forge?', 'no')
        ->assertExitCode(0);
});
