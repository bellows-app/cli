<?php

use Bellows\Config;

uses(Tests\TestCase::class);

function configTestingPath()
{
    return base_path('tests/stubs/config-testing');
}

beforeEach(function () {
    if (!is_dir(configTestingPath())) {
        mkdir(configTestingPath());
    }
});

afterEach(function () {
    if (file_exists(configTestingPath() . '/config.json')) {
        unlink(configTestingPath() . '/config.json');
    }

    if (is_dir(configTestingPath())) {
        rmdir(configTestingPath());
    }
});

it('will create the config file and directory if it does not exist', function () {
    if (is_dir(configTestingPath())) {
        rmdir(configTestingPath());
    }

    expect(file_exists(configTestingPath() . '/config.json'))->toBeFalse();

    new Config(configTestingPath());

    expect(file_exists(configTestingPath() . '/config.json'))->toBeTrue();
});

it('can get a value by its key', function () {
    file_put_contents(configTestingPath() . '/config.json', json_encode([
        'foo' => 'bar',
    ]));

    $config = new Config(configTestingPath());

    expect($config->get('foo'))->toBe('bar');
});

it('can set a config key and value', function () {
    $config = new Config(configTestingPath());

    $config->set('foo', 'bar');

    expect($config->get('foo'))->toBe('bar');
});

it('can remove a config key', function () {
    file_put_contents(configTestingPath() . '/config.json', json_encode([
        'foo' => 'bar',
    ]));

    $config = new Config(configTestingPath());

    expect($config->get('foo'))->toBe('bar');

    $config->remove('foo');

    expect($config->get('foo'))->toBeNull();
});
