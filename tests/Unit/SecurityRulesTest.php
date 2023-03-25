<?php

use Bellows\Plugins\SecurityRules;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

beforeEach(function () {
    Http::fake();
});

it('can create a single security rule', function () {
    $mock = $this->plugin()
        ->mockSite(function (MockInterface $mock) {
            $mock->shouldReceive('addSecurityRule')->once()->with(
                Mockery::on(
                    function ($rule) {
                        return $rule->toArray() === [
                            'name'          => 'Restricted Access',
                            'path'          => null,
                            'credentials'   => [
                                [
                                    'username' => 'joe',
                                    'password' => 'secretstuff',
                                ],
                            ],
                        ];
                    }
                )
            );
        })
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

    $plugin = app(SecurityRules::class);
    $plugin->setup();

    $mock->validate();

    $plugin->wrapUp();
})->group('plugin');

it('can add multiple users to a security group', function () {
    $mock = $this->plugin()
        ->mockSite(function (MockInterface $mock) {
            $mock->shouldReceive('addSecurityRule')->once()->with(
                Mockery::on(
                    function ($rule) {
                        return $rule->toArray() === [
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
                        ];
                    }
                )
            );
        })
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

    $plugin = app(SecurityRules::class);
    $plugin->setup();

    $mock->validate();

    $plugin->wrapUp();
})->group('plugin');

it('can create multiple security rules', function () {
    $mock = $this->plugin()

        ->mockSite(function (MockInterface $mock) {
            $mock->shouldReceive('addSecurityRule')->once()->with(
                Mockery::on(
                    function ($rule) {
                        return $rule->toArray() === [
                            'name'          => 'Restricted Access',
                            'path'          => null,
                            'credentials'   => [
                                [
                                    'username' => 'joe',
                                    'password' => 'secretstuff',
                                ],
                            ],
                        ];
                    }
                )
            );

            $mock->shouldReceive('addSecurityRule')->once()->with(
                Mockery::on(
                    function ($rule) {
                        return $rule->toArray() === [
                            'name'          => 'Admins',
                            'path'          => null,
                            'credentials'   => [
                                [
                                    'username' => 'gary',
                                    'password' => 'shhh',
                                ],
                            ],
                        ];
                    }
                )
            );
        })
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

    $plugin = app(SecurityRules::class);
    $plugin->setup();

    $mock->validate();

    $plugin->wrapUp();
})->group('plugin');
