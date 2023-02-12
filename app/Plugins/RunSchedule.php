<?php

namespace App\Plugins;

use App\Bellows\Data\DefaultEnabledDecision;
use App\Bellows\Data\Job;
use App\Bellows\Enums\JobFrequency;
use App\Bellows\Plugin;

class RunSchedule extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to run your artisan schedule');
    }

    public function enabled(): bool
    {
        return true;
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
