<?php

use Bellows\Console;
use Bellows\PluginManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

// beforeEach(function () use ($servers, $sites, $phpVersions, $site) {
//     Http::preventStrayRequests();
//     Process::preventStrayProcesses();

//     Process::fake();

//     // Process::fake([
//     //     'pwd' => Process::result(dirname(__DIR__, 1) . '/test-app'),
//     // ]);
// });

// // Ensure that when no plugins are active it can just create a site
// // Ensure that when something comes back from plugins, it is integrated into the data sent to Forge and that the prompts are correct
// // Create a fake plugin called BusyBody and it returns all possible things that can be set, make sure that it is integrated into the data sent to Forge and that the prompts are correct

// it('launches sites', function () {
//     fakeForgeRequests($server[1], $site);

//     cdTo('test-app');

//     app()->instance(PluginManager::class, new PluginManager(
//         app(Console::class),
//         []
//     ));

//     $this->artisan('launch')
//         ->expectsQuestion('Which server would you like to use?', 'joe-codes')
//         ->expectsQuestion('App Name', 'Test Project')
//         ->expectsQuestion('Domain', 'testproject.com')
//         ->expectsQuestion('Isolated User', 'test_project')
//         ->expectsQuestion('Repository', 'joetannenbaum/test-project')
//         ->expectsQuestion('Repository Branch', 'main')
//         // ->expectsConfirmation('Continue with defaults?', 'yes')
//         ->expectsConfirmation('Would you like to add any of them? They will be added with their existing values.', 'no')
//         ->expectsConfirmation('Open site in Forge?', 'no')
//         ->assertExitCode(0);
// })->only();
