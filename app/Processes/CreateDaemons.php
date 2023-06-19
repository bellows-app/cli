<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Data\Daemon;
use Bellows\PluginSdk\Data\DaemonParams;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;

class CreateDaemons
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        $daemons = collect($deployment->manager->daemons())->map(
            fn (DaemonParams $daemon) => Daemon::from([
                'command'   => $daemon->command,
                'user'      => $daemon->user ?: Project::isolatedUser(),
                'directory' => $daemon->directory
                    ?: '/' . collect([
                        'home',
                        Project::isolatedUser(),
                        Project::domain(),
                    ])->join('/'),
            ])
        );

        if ($daemons->isEmpty()) {
            return $next($deployment);
        }

        Console::step('Daemons');

        Console::withSpinner(
            title: 'Creating',
            task: fn () => $daemons->each(
                fn ($daemon) => $deployment->server->createDaemon($daemon),
            ),
        );

        $deployment->summary[] = ['Daemons', $daemons->pluck('command')->join(PHP_EOL)];

        return $next($deployment);
    }
}
