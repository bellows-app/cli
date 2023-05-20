<?php

use Bellows\Plugins\Mailgun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can create a new domain', function () {
    Http::fake([
        'domains' => Http::response(null, 200),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Which region is your Mailgun account in?', 'US')
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create a new domain?', 'yes')
        ->expectsQuestion('What is the domain name?', 'mail.bellowstest.com')
        ->setup();

    $plugin = app(Mailgun::class);
    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'MAIL_MAILER'      => 'mailgun',
        'MAILGUN_DOMAIN'   => 'mail.bellowstest.com',
        'MAILGUN_SECRET'   => '1b1c897b6b99c08558b3c75f37695532-b0aac6d0-0cf7a3c2',
        'MAILGUN_ENDPOINT' => 'api.mailgun.net',
    ]);

    Http::assertSent(function ($request) {
        return Str::contains($request->url(), 'domains')
            && $request->data() === [
                'name' => 'mail.bellowstest.com',
            ];
    });
});

it('can choose an existing domain', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Which region is your Mailgun account in?', 'US')
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create a new domain?', 'no')
        ->expectsQuestion('Which domain do you want to use?', 'sandbox316e51ab1d3f41e2a8be2d001a038e59.mailgun.org (sandbox)')
        ->setup();

    $plugin = app(Mailgun::class);
    $plugin->launch();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'MAIL_MAILER'      => 'mailgun',
        'MAILGUN_DOMAIN'   => 'sandbox316e51ab1d3f41e2a8be2d001a038e59.mailgun.org',
        'MAILGUN_SECRET'   => '1b1c897b6b99c08558b3c75f37695532-b0aac6d0-0cf7a3c2',
        'MAILGUN_ENDPOINT' => 'api.mailgun.net',
    ]);
});
