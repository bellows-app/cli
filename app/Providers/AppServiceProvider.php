<?php

namespace Bellows\Providers;

use Bellows\Config;
use Bellows\Console;
use Bellows\Mixins\Console as MixinsConsole;
use Illuminate\Console\Command;
use Illuminate\Console\Signals;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Config::class, fn () => new Config);
        $this->app->singleton(Console::class, fn () => new Console);

        Signals::resolveAvailabilityUsing(function () {
            return $this->app->runningInConsole()
                && !$this->app->runningUnitTests()
                && extension_loaded('pcntl');
        });

        Command::mixin(new MixinsConsole);
    }
}
