<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\PluginJob;
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
            new PluginJob(
                command: Artisan::forJob('schedule:run'),
                frequency: JobFrequency::MINUTELY,
            ),
        ];
    }
}
