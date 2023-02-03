<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;
use App\DeployMate\JobFrequency;

class DatabaseWorker extends BasePlugin
{
    public function defaultEnabled(): array
    {
        return $this->defaultEnabledPayload(
            true,
            'You probably want to run a database worker',
        );
    }

    public function jobs($server, $site): array
    {
        return [
            [
                'command'   => $this->artisan->forJob('queue:restart'),
                'frequency' => JobFrequency::NIGHTLY->value,
            ],
        ];
    }

    public function workers($server, $site): array
    {
        return [
            [
                'connection'   => 'database',
                'timeout'      => 0,
                'sleep'        => 60,
                'tries'        => null,
                'processes'    => 1,
                'stopwaitsecs' => 10,
                'daemon'       => false,
                'force'        => false,
                'queue'        => 'default',
            ]
        ];
    }
}
