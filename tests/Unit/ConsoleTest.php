<?php

use Bellows\Console;

uses(Tests\PluginTestCase::class);

beforeEach(function () {
    $this->plugin()->setup();
});

it('it will assign the only choice as the default option when there is one', function () {
    // TODO: This is sort of a dumb test, and a dumb method?
    // Why offer choices if there is only one option?
    // Can't remember why I did this.

    $mock = $this->plugin()
        ->expectsChoice(
            'Which database would you like to use?',
            'mysql',
            ['mysql'],
        )
        ->setup();

    $result = app(Console::class)->choice('Which database would you like to use?', ['mysql']);

    $mock->validate();

    expect($result)->toBe('mysql');
});

it('can make a choice from a collection', function () {
    $mock = $this->plugin()
        ->expectsChoice(
            'Which database would you like to use?',
            'Postgres',
            ['MySQL', 'Postgres'],
        )
        ->setup();

    $result = app(Console::class)->choiceFromCollection(
        'Which database would you like to use?',
        collect([
            ['id' => 'mysql', 'name' => 'MySQL'],
            ['id' => 'postgres', 'name' => 'Postgres'],
        ]),
        'name',
    );

    $mock->validate();

    expect($result)->toBe(['id' => 'postgres', 'name' => 'Postgres']);
});
