<?php

namespace App\Plugins;

use App\DeployMate\Data\DefaultEnabledDecision;
use App\DeployMate\Data\Job;
use App\DeployMate\Data\Worker;
use App\DeployMate\Enums\JobFrequency;
use App\DeployMate\Plugin;

class DatabaseWorker extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to run a database worker');
    }

    public function jobs($server, $site): array
    {
        return [
            new Job(
                command: $this->artisan->forJob('queue:restart'),
                frequency: JobFrequency::NIGHTLY->value,
            ),
        ];
    }

    public function workers($server, $site): array
    {
        return [
            new Worker(
                connection: 'database',
                queue: 'default',
            ),
        ];
    }
}
