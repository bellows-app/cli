<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;
use App\DeployMate\JobFrequency;

class RunSchedule extends BasePlugin
{
    public function defaultEnabled(): array
    {
        return $this->defaultEnabledPayload(
            true,
            'You probably want to run your artisan schedule',
        );
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
