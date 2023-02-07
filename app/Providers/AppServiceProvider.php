<?php

namespace App\Providers;

use App\DeployMate\Config;
use App\DeployMate\Console;
use App\DeployMate\Data\ProjectConfig;
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
