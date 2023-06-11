<?php

namespace Bellows\Providers;

use Bellows\Config;
use Bellows\Config\BellowsConfig;
use Bellows\Console;
use Bellows\Deploy\CurrentDeployment;
use Bellows\Http;
use Bellows\Mixins\Console as MixinsConsole;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;
use Bellows\Plugins\PluginManager;
use Bellows\Plugins\PluginManagerInterface;
use Bellows\PluginSdk\Contracts\HttpClient;
use Bellows\Project;
use Bellows\ServerProviders\Forge\Forge;
use Bellows\ServerProviders\ServerProviderInterface;
use Bellows\Util\Artisan;
use Bellows\Util\Domain;
use Bellows\Util\Entity;
use Bellows\Util\Value;
use Bellows\Util\Vite;
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

        $this->app->bind(HttpClient::class, fn () => app(Http::class));
        $this->app->bind('composer_helper', fn () => app(Composer::class));
        $this->app->singleton('npm_helper', fn () => app(Npm::class));
        $this->app->bind('domain_helper', fn () => Domain::class);
        $this->app->singleton('project', fn () => new Project);
        $this->app->singleton('artisan_helper', fn () => new Artisan);
        $this->app->singleton('current_deployment', fn () => new CurrentDeployment);
        $this->app->singleton('value_helper', fn () => new Value);
        $this->app->singleton('vite_helper', fn () => new Vite);
        $this->app->bind('console_helper', fn () => app(Console::class));
        $this->app->bind('entity_helper', fn () => app(Entity::class));
        $this->app->bind(PluginManagerInterface::class, fn () => app(PluginManager::class));
        $this->app->bind(ServerProviderInterface::class, fn () => app(Forge::class));
    }
}
