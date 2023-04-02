<?php

use Bellows\Artisan;
use Bellows\Config;
use Bellows\Console;
use Bellows\Data\ProjectConfig;
use Bellows\DeployScript;
use Bellows\Http;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\Plugin;
use Bellows\PluginCommandRunner;
use Bellows\ServerProviders\ServerInterface;

uses(Tests\PluginTestCase::class);

it('can run a command on a collection of plugins', function () {
    $fakePlugin = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing(): array
        {
            return [
                'first one',
            ];
        }
    };

    $fakePlugin2 = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing(): array
        {
            return [
                'second one',
            ];
        }
    };

    $runner = new PluginCommandRunner(
        collect([$fakePlugin, $fakePlugin2]),
        'testing',
    );

    $result = $runner->run();

    expect($result->toArray())->toBe([
        ['first one'],
        ['second one'],
    ]);
});

it('can run a command on a collection of plugins with arguments', function () {
    $fakePlugin = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing($name): array
        {
            return [
                'first one ' . $name,
            ];
        }
    };

    $fakePlugin2 = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing($name): array
        {
            return [
                'second one ' . $name,
            ];
        }
    };

    $runner = new PluginCommandRunner(
        collect([$fakePlugin, $fakePlugin2]),
        'testing',
    );

    $result = $runner->withArgs('gary')->run();

    expect($result->toArray())->toBe([
        ['first one gary'],
        ['second one gary'],
    ]);
});

it('can reduce an array', function () {
    $fakePlugin = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing(): array
        {
            return [
                'first' => 'first one',
            ];
        }
    };

    $fakePlugin2 = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing(): array
        {
            return [
                'second' => 'second one',
            ];
        }
    };

    $runner = new PluginCommandRunner(
        collect([$fakePlugin, $fakePlugin2]),
        'testing',
    );

    $result = $runner->reduce([]);

    expect($result)->toBe([
        'first' => 'first one',
        'second' => 'second one',
    ]);
});

it('can reduce a string', function () {
    $fakePlugin = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing($val): string
        {
            return str_replace('initial', 'first', $val);
        }
    };

    $fakePlugin2 = new class(
        app(ProjectConfig::class),
        app(Config::class),
        app(Http::class),
        app(Console::class),
        app(Composer::class),
        app(Npm::class),
        app(DeployScript::class),
        app(Artisan::class),
        app(ServerInterface::class),
    ) extends Plugin
    {
        public function testing($val): string
        {
            return str_replace('value', 'second', $val);
        }
    };

    $runner = new PluginCommandRunner(
        collect([$fakePlugin, $fakePlugin2]),
        'testing',
    );

    $result = $runner->reduce('initial string value');

    expect($result)->toBe('first string second');
});
