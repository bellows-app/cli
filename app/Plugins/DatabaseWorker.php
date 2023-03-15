<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\Job;
use Bellows\Data\Worker;
use Bellows\Enums\JobFrequency;
use Bellows\Plugin;

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
