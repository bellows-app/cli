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

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return $this->server->hasJob(Artisan::forJob('schedule:run'));
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
