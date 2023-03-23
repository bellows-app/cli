<?php

use Bellows\Plugins\SecurityRules;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::macro(
        'forgeSite',
        fn () => Http::baseUrl('https://forge.laravel.com/api/v1')
            ->acceptJson()
            ->asJson()
    );
});

it('can create a single security rule', function () {
    Http::fake([
        'security-rules' => Http::response(null, 200),
    ]);

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

    $plugin = app(SecurityRules::class);
    $plugin->setup();

    $mock->validate();

    $plugin->wrapUp();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/security-rules'
            && $request->data() === [
                'name'    => 'Restricted Access',
                'path'    => null,
                'credentials'   => [
                    [
                        'username' => 'joe',
                        'password' => 'secretstuff',
                    ],
                ],
            ];
    });
})->group('plugin');

it('can add multiple users to a security group', function () {
    Http::fake([
        'security-rules' => Http::response(null, 200),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Security rule group name', 'Restricted Access')
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

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/security-rules'
            && $request->data() === [
                'name'    => 'Restricted Access',
                'path'    => 'stripe/*',
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
    });
})->group('plugin');

it('can create multiple security rules', function () {
    Http::fake([
        'security-rules' => Http::response(null, 200),
    ]);

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

    $plugin = app(SecurityRules::class);
    $plugin->setup();

    $mock->validate();

    $plugin->wrapUp();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/security-rules'
            && $request->data() === [
                'name'    => 'Restricted Access',
                'path'    => null,
                'credentials'   => [
                    [
                        'username' => 'joe',
                        'password' => 'secretstuff',
                    ],
                ],
            ];
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://forge.laravel.com/api/v1/security-rules'
            && $request->data() === [
                'name'    => 'Admins',
                'path'    => null,
                'credentials'   => [
                    [
                        'username' => 'gary',
                        'password' => 'shhh',
                    ],
                ],
            ];
    });
})->group('plugin');
