<?php

namespace App\Plugins;

use App\DeployMate\DefaultEnabledDecision;
use App\DeployMate\Plugin;
use App\DeployMate\JobFrequency;

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

    public function jobs($server, $site): array
    {
        return [
            [
                'command'   => $this->artisan->forJob('schedule:run'),
                'frequency' => JobFrequency::MINUTELY->value,
            ],
        ];
    }
}
