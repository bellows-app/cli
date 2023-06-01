<?php

namespace Bellows\Providers;

use Bellows\Config;
use Bellows\Config\BellowsConfig;
use Bellows\Console;
use Bellows\Mixins\Console as MixinsConsole;
use Bellows\Plugins\PluginManager;
use Bellows\Plugins\PluginManagerInterface;
use Bellows\Project;
use Bellows\ServerProviders\Forge\Forge;
use Bellows\ServerProviders\ServerProviderInterface;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Signals;
use Illuminate\Support\Facades\Process;
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
            collect([
                BellowsConfig::getInstance()->path(''),
                BellowsConfig::getInstance()->path('logs'),
            ])->filter(fn ($d) => !is_dir($d))->each(fn ($d) => mkdir($d));
        }
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

        Process::macro('runWithOutput', function ($command) {
            Process::run($command, function ($type, $output) {
                echo $output;
            });
        });

        Signals::resolveAvailabilityUsing(function () {
            return $this->app->runningInConsole()
                && !$this->app->runningUnitTests()
                && extension_loaded('pcntl');
        });

        Console::mixin(new MixinsConsole);
        Command::mixin(new MixinsConsole);

        $this->app->singleton('project', fn () => new Project);
        $this->app->bind('console', fn () => app(Console::class));
        $this->app->bind(PluginManagerInterface::class, fn () => app(PluginManager::class));
        $this->app->bind(ServerProviderInterface::class, fn () => app(Forge::class));
    }
}
