<?php

namespace App\Plugins;

use App\Bellows\Data\DefaultEnabledDecision;
use App\Bellows\Data\Job;
use App\Bellows\Data\Worker;
use App\Bellows\Enums\JobFrequency;
use App\Bellows\Plugin;

class DatabaseWorker extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to run a database worker');
    }

    public function jobs(): array
    {
        return [
            new Job(
                command: $this->artisan->forJob('queue:restart'),
                frequency: JobFrequency::NIGHTLY,
            ),
        ];
    }

    public function workers(): array
    {
        return [
            new Worker(
                connection: 'database',
                queue: 'default',
            ),
        ];
    }
}
