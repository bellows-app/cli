<?php

namespace App\Providers;

use App\Bellows\Config;
use App\Bellows\Console;
use App\Bellows\Data\ProjectConfig;
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
    }
}
