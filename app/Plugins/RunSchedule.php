<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\Job;
use Bellows\Enums\JobFrequency;
use Bellows\Plugin;

class RunSchedule extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to run your artisan schedule');
    }

    public function jobs(): array
    {
        return [
            new Job(
                command: $this->artisan->forJob('schedule:run'),
                frequency: JobFrequency::MINUTELY,
            ),
        ];
    }
}
