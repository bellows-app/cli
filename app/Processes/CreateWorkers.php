<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Data\Worker;
use Bellows\PluginSdk\Data\WorkerParams;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;

class CreateWorkers
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        $workers = collect($deployment->manager->workers())->map(
            fn (WorkerParams $worker) => Worker::from(
                array_merge(
                    $worker->toArray(),
                    ['php_version' => $worker->phpVersion ?? Project::phpVersion()->version],
                )
            )
        );

        if ($workers->isEmpty()) {
            return $next($deployment);
        }

        Console::step('Workers');

        Console::withSpinner(
            title: 'Creating',
            task: fn () => $workers->each(
                fn ($worker) => $deployment->site->createWorker($worker)
            ),
        );

        $deployment->summary[] = ['Workers', $workers->pluck('connection')->join(PHP_EOL)];

        return $next($deployment);
    }
}
