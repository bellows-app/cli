<?php

namespace Bellows\Providers;

use Bellows\Config;
use Bellows\Console;
use Bellows\Mixins\Console as MixinsConsole;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Signals;
use Illuminate\Support\ServiceProvider;
use Phar;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (Phar::running()) {
            if (!is_dir(config('app.home_dir') . '/.bellows')) {
                mkdir(config('app.home_dir') . '/.bellows');
            }

            if (!is_dir(config('app.home_dir') . '/.bellows/logs')) {
                mkdir(config('app.home_dir') . '/.bellows/logs');
            }
        }

        config([
            'logging.channels.single.path' => Phar::running()
                ? config('app.home_dir') . '/.bellows/logs/cli.log'
                : storage_path('logs/laravel.log'),
        ]);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Config::class, fn () => new Config);

        $this->app->singleton(
            Console::class,
            fn () => new Console(
                app(
                    OutputStyle::class,
                    ['input' => new ArgvInput, 'output' => new ConsoleOutput],
                )
            )
        );

        Signals::resolveAvailabilityUsing(function () {
            return $this->app->runningInConsole()
                && !$this->app->runningUnitTests()
                && extension_loaded('pcntl');
        });

        Console::mixin(new MixinsConsole);
        Command::mixin(new MixinsConsole);
    }
}
