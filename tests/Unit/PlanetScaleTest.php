<?php

use Bellows\Plugins\PlanetScale;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can select from an existing database and branch', function () {
    Http::fake([
        'organizations/joe-codes/databases/bellows-tester/branches/main/passwords' => Http::response([
            'connection_strings' => [
                'laravel' => <<<'ENV'
DB_CONNECTION=mysql
DB_HOST=aws.connect.psdb.cloud
DB_PORT=3306
DB_DATABASE=bellows-tester
DB_USERNAME=test-username
DB_PASSWORD=test-password
MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem
ENV
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'main')
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_HOST'              => 'aws.connect.psdb.cloud',
        'DB_PORT'              => '3306',
        'DB_DATABASE'          => 'bellows-tester',
        'DB_USERNAME'          => 'test-username',
        'DB_PASSWORD'          => 'test-password',
        'MYSQL_ATTR_SSL_CA'    => '/etc/ssl/certs/ca-certificates.crt',
    ]);
});

it('can create a new database', function () {
    Http::fake([
        'organizations/joe-codes/databases' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'bellows-tester',
                    ],
                ],
            ],
            [
                'name' => 'new-db',
            ],
        ]),
        'organizations/joe-codes/databases/new-db/branches' => Http::response([
            'data' => [
                [
                    'name' => 'main',
                ],
            ],
        ]),
        'organizations/joe-codes/databases/new-db/branches/main/passwords' => Http::response([
            'connection_strings' => [
                'laravel' => <<<'ENV'
DB_CONNECTION=mysql
DB_HOST=aws.connect.psdb.cloud
DB_PORT=3306
DB_DATABASE=new-db
DB_USERNAME=test-username
DB_PASSWORD=test-password
MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem
ENV
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'Create new database')
        ->expectsQuestion('Database name', 'new-db')
        ->expectsConfirmation('Once you have added the scopes: Continue?', 'yes')
        ->expectsQuestion('Branch', 'main')
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_HOST'              => 'aws.connect.psdb.cloud',
        'DB_PORT'              => '3306',
        'DB_DATABASE'          => 'new-db',
        'DB_USERNAME'          => 'test-username',
        'DB_PASSWORD'          => 'test-password',
        'MYSQL_ATTR_SSL_CA'    => '/etc/ssl/certs/ca-certificates.crt',
    ]);

    Http::assertSent(fn ($request) => matchesRequest(
        $request,
        '/v1/organizations/joe-codes/databases',
        'post',
        ['name' => 'new-db'],
    ));
});

it('can create a new branch', function () {
    Http::fake([
        'organizations/joe-codes/databases/bellows-tester/branches' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'main',
                    ],
                ],
            ],
            [
                'name' => 'new-branch',
            ],
        ]),
        'organizations/joe-codes/databases/bellows-tester/branches/new-branch/passwords' => Http::response([
            'connection_strings' => [
                'laravel' => <<<'ENV'
DB_CONNECTION=mysql
DB_HOST=aws.connect.psdb.cloud
DB_PORT=3306
DB_DATABASE=bellows-tester
DB_USERNAME=test-username
DB_PASSWORD=test-password
MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem
ENV
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'Create new branch')
        ->expectsQuestion('Branch name', 'new-branch')
        ->expectsQuestion('Parent branch', 'main')
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'DB_CONNECTION'        => 'mysql',
        'DB_HOST'              => 'aws.connect.psdb.cloud',
        'DB_PORT'              => '3306',
        'DB_DATABASE'          => 'bellows-tester',
        'DB_USERNAME'          => 'test-username',
        'DB_PASSWORD'          => 'test-password',
        'MYSQL_ATTR_SSL_CA'    => '/etc/ssl/certs/ca-certificates.crt',
    ]);

    Http::assertSent(fn ($request) => matchesRequest(
        $request,
        '/v1/organizations/joe-codes/databases/bellows-tester/branches',
        'post',
        ['name' => 'new-branch', 'parent_branch' => 'main'],
    ));
});

it('will bark if it does not have the correct database scopes', function () {
    Http::fake([
        'organizations/joe-codes/databases' => Http::response([
            'code' => 'forbidden',
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsOutput("Bellows doesn't have permission to list databases.")
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();
});

it('will bark if it does not have the correct create database scopes', function () {
    Http::fake([
        'organizations/joe-codes/databases' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'bellows-tester',
                    ],
                ],
            ],
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'Create new database')
        ->expectsQuestion('Database name', 'new-db')
        ->expectsOutput("Bellows doesn't have permission to create a database.")
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();
});

it('will bark if it does not have the correct list branches scopes', function () {
    Http::fake([
        'organizations/joe-codes/databases/bellows-tester/branches' => Http::sequence([
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsOutput("Bellows doesn't have permission to list database branches.")
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();
});

it('will bark if it does not have the correct create branch scopes', function () {
    Http::fake([
        'organizations/joe-codes/databases/bellows-tester/branches' => Http::sequence([
            [
                'data' => [
                    [
                        'name' => 'main',
                    ],
                ],
            ],
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'Create new branch')
        ->expectsQuestion('Branch name', 'new-branch')
        ->expectsQuestion('Parent branch', 'main')
        ->expectsOutput("Bellows doesn't have permission to create a branch.")
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();
});

it('will bark if it does not have the correct create password scopes', function () {
    Http::fake([
        'organizations/joe-codes/databases/bellows-tester/branches/main/passwords' => Http::sequence([
            [
                'code' => 'forbidden',
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsQuestion('Database', 'bellows-tester')
        ->expectsQuestion('Branch', 'main')
        ->expectsOutput("Bellows doesn't have permission to create a password.")
        ->setup();

    $plugin = app(PlanetScale::class);
    $plugin->launch();

    $mock->validate();
});
