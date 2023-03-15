<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\Worker;
use Bellows\Plugin;

class DatabaseWorker extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to run a database worker');
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
