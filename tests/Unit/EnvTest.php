<?php

use Bellows\Env;

uses(Tests\TestCase::class);

it('can get an existing value from the env', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    expect($env->get('FOO'))->toBe('bar');
    expect($env->get('APP_URL'))->toBe('https://example.com');
});

it('will default to null if the key does not exist', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    expect($env->get('WHAT'))->toBeNull();
});

it('can get all of the values', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    expect($env->all())->toBe([
        'FOO'     => 'bar',
        'APP_URL' => 'https://example.com',
    ]);
});

it('can get update a value', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    $newEnv = $env->update('FOO', 'baz');

    expect($env->get('FOO'))->toBe('baz');

    expect($newEnv)->toBe(<<<'ENV'
FOO=baz
APP_URL=https://example.com
ENV);
});

it('can quote a value when it is referencing another value', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    $newEnv = $env->update('FOO', '${APP_URL}');

    expect($env->get('FOO'))->toBe('${APP_URL}');

    expect($newEnv)->toBe(<<<'ENV'
FOO="${APP_URL}"
APP_URL=https://example.com
ENV);
});

it('will keep quotes if the new value has them already', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    $newEnv = $env->update('FOO', '"THIS THOUGH"');

    expect($env->get('FOO'))->toBe('THIS THOUGH');

    expect($newEnv)->toBe(<<<'ENV'
FOO="THIS THOUGH"
APP_URL=https://example.com
ENV);
});

it('will quote a password by default', function () {
    $env = new Env(<<<'ENV'
FOO_PASSWORD=bar
APP_URL=https://example.com
ENV);

    $newEnv = $env->update('FOO_PASSWORD', 'secretstuff');

    expect($env->get('FOO_PASSWORD'))->toBe('secretstuff');

    expect($newEnv)->toBe(<<<'ENV'
FOO_PASSWORD="secretstuff"
APP_URL=https://example.com
ENV);
});

it('will quote a string with spaces by default', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    $newEnv = $env->update('FOO', 'now with a space');

    expect($env->get('FOO'))->toBe('now with a space');

    expect($newEnv)->toBe(<<<'ENV'
FOO="now with a space"
APP_URL=https://example.com
ENV);
});

it('will return the raw env as a string', function () {
    $env = new Env(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);

    expect($env->toString())->toBe(<<<'ENV'
FOO=bar
APP_URL=https://example.com
ENV);
});

it('will group related keys', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
FOO=bar
ENV);

    $newEnv = $env->update('APP_NAME', 'my site');

    expect($newEnv)->toBe(<<<'ENV'
APP_URL=https://example.com
APP_NAME="my site"
FOO=bar
ENV);
});

it('will group related vite keys', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
FOO=bar
ENV);

    $newEnv = $env->update('VITE_APP_NAME', 'my site');

    expect($newEnv)->toBe(<<<'ENV'
APP_URL=https://example.com
VITE_APP_NAME="my site"
FOO=bar
ENV);
});

it('will group related mix keys', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
FOO=bar
ENV);

    $newEnv = $env->update('MIX_APP_NAME', 'my site');

    expect($newEnv)->toBe(<<<'ENV'
APP_URL=https://example.com
MIX_APP_NAME="my site"
FOO=bar
ENV);
});

it('will keep a true value as is', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
FOO=bar
ENV);

    $newEnv = $env->update('ENABLED', true);

    expect($newEnv)->toBe(<<<'ENV'
APP_URL=https://example.com
FOO=bar

ENABLED=true
ENV);
});

it('will keep a false value as is', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
FOO=bar
ENV);

    $newEnv = $env->update('ENABLED', false);

    expect($newEnv)->toBe(<<<'ENV'
APP_URL=https://example.com
FOO=bar

ENABLED=false
ENV);
});

it('can check if an env has all of the requested keys', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
FOO=bar
ENV);

    expect($env->hasAll('APP_URL', 'FOO'))->toBeTrue();
});

it('can check if an env does not have all of the requested keys', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
ENV);

    expect($env->hasAll('APP_URL', 'FOO'))->toBeFalse();
});

it('can check if an env has any of the requested keys', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
ENV);

    expect($env->hasAny('APP_URL', 'FOO'))->toBeTrue();
});

it('can check if an env does not have any of the requested keys', function () {
    $env = new Env(<<<'ENV'
APP_URL=https://example.com
FOO=bar
ENV);

    expect($env->hasAny('BLAH', 'WHATEVER'))->toBeFalse();
});
