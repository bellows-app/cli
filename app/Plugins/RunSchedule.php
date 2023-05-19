<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\PluginJob;
use Bellows\Enums\JobFrequency;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;

class RunSchedule extends Plugin implements Launchable, Deployable
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to run your artisan schedule');
    }

    public function launch(): void
    {
        // Nothing to do here
    }

    public function deploy(): void
    {
        // Nothing to do here
    }

    public function canDeploy(): bool
    {
        // TODO: Check if job is already scheduled?
        return false;
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
