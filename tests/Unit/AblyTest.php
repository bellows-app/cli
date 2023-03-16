<?php

use Bellows\Config;
use Bellows\Console;
use Bellows\Data\ForgeServer;
use Bellows\Data\ProjectConfig;
use Bellows\Dns\Cloudflare;
use Bellows\Dns\DigitalOcean;
use Bellows\Dns\DnsFactory;
use Bellows\Dns\GoDaddy;
use Bellows\Plugins\Ably;
use Illuminate\Console\BufferedConsoleOutput;
use Illuminate\Console\OutputStyle as ConsoleOutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

beforeEach(function () {
    $this->app->bind(
        Config::class,
        fn () => new Config(__DIR__ . '/../stubs/config'),
    );

    $this->app->bind(OutputInterface::class, function () {
        return new BufferedConsoleOutput();
    });

    $this->app->bind(InputInterface::class, function () {
        return new ArgvInput();
    });

    $this->app->bind(ProjectConfig::class, function () {
        return new ProjectConfig(
            isolatedUser: 'tester',
            repositoryUrl: 'bellows/tester',
            repositoryBranch: 'main',
            phpVersion: '8.1',
            phpBinary: 'php81',
            projectDirectory: __DIR__ . '/../stubs/plugins/ably',
            domain: 'bellowstester.com',
            appName: 'Bellows Tester',
            secureSite: true,
        );
    });

    $this->app->bind(ForgeServer::class, function () {
        return ForgeServer::from([
            'id'         => 123,
            'name'       => 'test-server',
            'type'       => 'php',
            'ip_address' => '123.123.123.123',
        ]);
    });

    $this->app->bind(
        Console::class,
        function () {
            $console = new Console();

            $console->setOutput(
                app(ConsoleOutputStyle::class)
            );

            return $console;
        }
    );

    Http::fake([]);
});

it('can boot up the plugin', function () {
    dd(app(Ably::class));
})->group('plugin');
