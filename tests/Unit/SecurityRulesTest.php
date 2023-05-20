<?php

use Bellows\Plugins\SecurityRules;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Facades\Http;

uses(Tests\PluginTestCase::class)->group('plugin');

beforeEach(function () {
    Http::fake();
});

it('can create a single security rule', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Security rule group name', 'Restricted Access')
        ->expectsQuestion(
            'Path (leave blank to password protect all routes within your site, any valid Nginx location path)',
            null
        )
        ->expectsQuestion('Username', 'joe')
        ->expectsQuestion('Password', 'secretstuff')
        ->expectsConfirmation('Add another user?', 'no')
        ->expectsConfirmation('Add another security rule group?', 'no')
        ->setup();

    $site = app(SiteInterface::class);

    $plugin = app(SecurityRules::class);
    $plugin->setSite($site);
    $plugin->launch();

    $mock->validate();

    $plugin->wrapUp();

    $site->assertMethodWasCalled(
        'addSecurityRule',
        fn ($args) => $args[0]->toArray() === [
            'name'          => 'Restricted Access',
            'path'          => null,
            'credentials'   => [
                [
                    'username' => 'joe',
                    'password' => 'secretstuff',
                ],
            ],
        ],
    );
});

it('can add multiple users to a security group', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Security rule group name', 'Stripe Webhook')
        ->expectsQuestion(
            'Path (leave blank to password protect all routes within your site, any valid Nginx location path)',
            'stripe/*'
        )
        ->expectsQuestion('Username', 'joe')
        ->expectsQuestion('Password', 'secretstuff')
        ->expectsConfirmation('Add another user?', 'yes')
        ->expectsQuestion('Username', 'frank')
        ->expectsQuestion('Password', 'noway')
        ->expectsConfirmation('Add another user?', 'no')
        ->expectsConfirmation('Add another security rule group?', 'no')
        ->setup();

    $site = app(SiteInterface::class);

    $plugin = app(SecurityRules::class);
    $plugin->setSite($site);
    $plugin->launch();

    $mock->validate();

    $plugin->wrapUp();

    $site->assertMethodWasCalled(
        'addSecurityRule',
        fn ($args) => $args[0]->toArray() === [
            'name'          => 'Stripe Webhook',
            'path'          => 'stripe/*',
            'credentials'   => [
                [
                    'username' => 'joe',
                    'password' => 'secretstuff',
                ],
                [
                    'username' => 'frank',
                    'password' => 'noway',
                ],
            ],
        ],
    );
});

it('can create multiple security rules', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Security rule group name', 'Restricted Access')
        ->expectsQuestion(
            'Path (leave blank to password protect all routes within your site, any valid Nginx location path)',
            null
        )
        ->expectsQuestion('Username', 'joe')
        ->expectsQuestion('Password', 'secretstuff')
        ->expectsConfirmation('Add another user?', 'no')
        ->expectsConfirmation('Add another security rule group?', 'yes')
        ->expectsQuestion('Security rule group name', 'Admins')
        ->expectsQuestion(
            'Path (leave blank to password protect all routes within your site, any valid Nginx location path)',
            null
        )
        ->expectsQuestion('Username', 'gary')
        ->expectsQuestion('Password', 'shhh')
        ->expectsConfirmation('Add another user?', 'no')
        ->expectsConfirmation('Add another security rule group?', 'no')
        ->setup();

    $site = app(SiteInterface::class);

    $plugin = app(SecurityRules::class);
    $plugin->setSite($site);
    $plugin->launch();

    $mock->validate();

    $plugin->wrapUp();

    $site->assertMethodWasCalled(
        'addSecurityRule',
        fn ($args) => $args[0]->toArray() === [
            'name'          => 'Restricted Access',
            'path'          => null,
            'credentials'   => [
                [
                    'username' => 'joe',
                    'password' => 'secretstuff',
                ],
            ],
        ]
    );

    $site->assertMethodWasCalled(
        'addSecurityRule',
        fn ($args) => $args[0]->toArray() === [
            'name'          => 'Admins',
            'path'          => null,
            'credentials'   => [
                [
                    'username' => 'gary',
                    'password' => 'shhh',
                ],
            ],
        ],
    );
});
