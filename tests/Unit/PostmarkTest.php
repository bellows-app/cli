<?php

use Bellows\Plugins\Postmark;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('can create a new user and database', function () {
    Http::fake([
        'servers' => Http::response([
            'ID'        => 1,
            'Name'      => 'Test Server',
            'ApiTokens' => [
                'test-api-token',
            ],
        ]),
        'domains' => Http::response([
            'ID'                       => 1,
            'Name'                     => 'mail.bellowstest.com',
            'ReturnPathDomainVerified' => true,
            'DKIMVerified'             => true,
        ]),
        'message-streams' => Http::response([
            'MessageStreams' => [
                [
                    'ID'          => 1,
                    'Name'        => 'Transactional',
                    'Description' => 'Transactional emails',
                ],
                [
                    'ID'          => 2,
                    'Name'        => 'Outbound',
                    'Description' => 'Outbound emails',
                ],
            ],
        ]),
    ]);

    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create new Postmark server?', 'yes')
        ->expectsQuestion('Server name', 'Test Server')
        ->expectsQuestion('Server color', 'Purple')
        ->expectsConfirmation('Create new Postmark domain?', 'yes')
        ->expectsQuestion('Domain name', 'mail.bellowstest.com')
        ->expectsChoice('Which Postmark message stream', '1', [
            '1' => 'Transactional (Transactional emails)',
            '2' => 'Outbound (Outbound emails)',
        ])
        ->expectsQuestion('From email', 'hello@mail.bellowstest.com')
        ->setup();

    $plugin = app(Postmark::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'MAIL_MAILER'                => 'postmark',
        'MAIL_FROM_ADDRESS'          => 'hello@mail.bellowstest.com',
        'POSTMARK_MESSAGE_STREAM_ID' => '1',
        'POSTMARK_TOKEN'             => 'test-api-token',
    ]);

    Http::assertSent(function ($request) {
        return Str::contains($request->url(), 'servers')
            && $request->method() === 'POST'
            && $request->data() === [
                'Name'  => 'Test Server',
                'Color' => 'Purple',
            ];
    });

    Http::assertSent(function ($request) {
        return Str::contains($request->url(), 'domains')
            && $request->method() === 'POST'
            && $request->data() === [
                'Name' => 'mail.bellowstest.com',
            ];
    });
})->group('plugin');

it('can select an existing server and domain from the list', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Select account', 'joe')
        ->expectsConfirmation('Create new Postmark server?', 'no')
        ->expectsQuestion('Choose a Postmark server', 'Forge It Test')
        ->expectsConfirmation('Create new Postmark domain?', 'no')
        ->expectsQuestion('Choose a Postmark sender domain', 'mail.forgeittest.joe.codes')
        ->expectsQuestion('Which Postmark message stream', 'outbound')
        ->expectsQuestion('From email', 'hello@mail.forgeittest.joe.codes')
        ->setup();

    $plugin = app(Postmark::class);
    $plugin->setup();

    $mock->validate();

    expect($plugin->environmentVariables())->toBe([
        'MAIL_MAILER'                => 'postmark',
        'MAIL_FROM_ADDRESS'          => 'hello@mail.forgeittest.joe.codes',
        'POSTMARK_MESSAGE_STREAM_ID' => 'outbound',
        'POSTMARK_TOKEN'             => '77138ddb-8679-489b-9f9c-b624a13d192e',
    ]);
})->group('plugin');
