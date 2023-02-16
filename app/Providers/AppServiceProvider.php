<?php

namespace App\Providers;

use App\Bellows\Config;
use App\Bellows\Console;
use App\Mixins\Console as MixinsConsole;
use Illuminate\Console\Command;
use Illuminate\Console\Signals;
use Illuminate\Support\ServiceProvider;
use Spatie\Async\Pool;

use function Termwind\render;
use function Termwind\terminal;

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

        // https://antofthy.gitlab.io/info/ascii/Spinners.txt
        // $animation = collect(mb_str_split(" ․‥…"));
        // $animation = collect(mb_str_split(" ․‥…"));
        // $animation = collect(mb_str_split("◜◠◝◞◡◟"));
        // Leap frog
        // $animation = collect(mb_str_split('⣀⢄⢂⢁⡈⡐⡠'));
        // $animation = collect(mb_str_split('⣀⡠⠤⠢⠒⠊⠉⠑⠒⠔⠤⢄'));
        // $animation = collect(mb_str_split('⠈⠘⠨⢈⡈⠌⠊⠉⠘⠐⠰⢐⡐⠔⠒⠑⠨⠰⠠⢠⡠⠤⠢⠡⢈⢐⢠⢀⣀⢄⢂⢁⡈⡐⡠⣀⡀⡄⡂⡁⠌⠔⠤⢄⡄⠠⠆⠅⠊⠒⠢⢂⡂⠆⠂⠃⠉⠑⠡⢁⡁⠅⠃⠁'));

        Command::mixin(new MixinsConsole);
    }
}
