<?php

use Bellows\Plugins\DigitalOceanDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can create a new user and database', function () {
    Http::fake([
        'databases/54526830-1694-46b3-99da-39376e35ba0e/users' => Http::response([
            'user' => [
                'password' => 'secretstuff',
            ],
        ]),
        'databases/54526830-1694-46b3-99da-39376e35ba0e/dbs' => Http::response(null, 200),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'test_app')
        ->expectsQuestion('Database User', 'test_user')
        ->setup();

    $plugin = app(DigitalOceanDatabase::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_DATABASE'          => 'test_app',
        'DB_USERNAME'          => 'test_user',
        'DB_HOST'              => 'private-joe-codes-primary-db-do-user-267404-0.a.db.ondigitalocean.com',
        'DB_PORT'              => '25060',
        'DB_PASSWORD'          => 'secretstuff',
        'DB_ALLOW_DISABLED_PK' => true,
    ]);

    Http::assertSent(function ($request) {
        return Str::contains($request->url(), 'databases/54526830-1694-46b3-99da-39376e35ba0e/dbs')
            && $request->data() === [
                'name' => 'test_app',
            ];
    });
});

it('can opt for an existing user and database', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'forge_it_test')
        ->expectsQuestion('Database User', 'forge_it_test')
        ->expectsConfirmation('User already exists, do you want to continue?', 'yes')
        ->expectsConfirmation('Database already exists, do you want to continue?', 'yes')
        ->setup();

    $plugin = app(DigitalOceanDatabase::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_DATABASE'          => 'forge_it_test',
        'DB_USERNAME'          => 'forge_it_test',
        'DB_HOST'              => 'private-joe-codes-primary-db-do-user-267404-0.a.db.ondigitalocean.com',
        'DB_PORT'              => '25060',
        'DB_PASSWORD'          => 'AVNS_OWsCMvRHbLc0mloU43l',
        'DB_ALLOW_DISABLED_PK' => true,
    ]);
});
