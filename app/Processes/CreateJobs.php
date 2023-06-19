<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Data\Job;
use Bellows\PluginSdk\Data\JobParams;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;

class CreateJobs
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        $jobs = collect($deployment->manager->jobs())->map(
            fn (JobParams $job) => Job::from(
                array_merge(
                    $job->toArray(),
                    ['user' => $job->user ?? Project::isolatedUser()],
                ),
            )
        );

        if ($jobs->isEmpty()) {
            return $next($deployment);
        }

        Console::step('Scheduled Jobs');

        Console::withSpinner(
            title: 'Creating',
            task: fn () => $jobs->each(
                fn ($job) => $deployment->server->createJob($job)
            ),
        );

        $deployment->summary[] = ['Scheduled Jobs', $jobs->pluck('command')->join(PHP_EOL)];

        return $next($deployment);
    }
}
