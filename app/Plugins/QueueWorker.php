<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\PluginWorker;
use Bellows\DeployScript;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Support\Facades\Process;

class QueueWorker extends Plugin implements Launchable, Deployable, Installable
{
    use CanBeInstalled;

    protected $queueWorkers = [];

    protected string $queueConnection;

    public function enabled(): bool
    {
        return Console::confirm('Do you want to set up any queue workers?');
    }

    public function launch(): void
    {
        $addAnother = true;

        $localConnection = Project::env()->get('QUEUE_CONNECTION', 'database');

        if ($localConnection === 'sync') {
            $localConnection = null;
        }

        do {
            $this->queueConnection = Console::anticipateRequired(
                'Connection',
                ['database', 'sqs', 'redis', 'beanstalkd', 'sync'],
                $localConnection
            );

            $queue = Console::ask('Queue', 'default');

            $params = $this->getParams();

            $worker = array_merge([
                'connection' => $this->queueConnection,
                'queue'      => $queue,
            ], collect($params)->mapWithKeys(
                fn ($item, $key) => [$key => $item['value']]
            )->toArray());

            $this->queueWorkers[] = PluginWorker::from($worker);

            $addAnother = Console::confirm('Do you want to add another queue worker?');

            // If we're adding another, we don't want to use default,
            // just offer that the first time
            $localConnection = null;
        } while ($addAnother);
    }

    public function install(): void
    {
        $this->queueConnection = Console::choice('Which queue driver would you like to use?', [
            'database',
            'sqs',
            'redis',
            'beanstalkd',
            'sync',
        ]);

        if ($this->queueConnection === 'database') {
            Process::runWithOutput(Artisan::local('queue:table'));
        }
    }

    public function deploy(): bool
    {
        $this->launch();

        return true;
    }

    public function workers(): array
    {
        return $this->queueWorkers;
    }

    public function canDeploy(): bool
    {
        return true;
    }

    public function environmentVariables(): array
    {
        if (isset($this->queueConnection)) {
            return [
                'QUEUE_CONNECTION' => $this->queueConnection,
            ];
        }

        return [];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addBeforePHPReload($deployScript, [
            Artisan::inDeployScript('queue:restart'),
        ]);
    }

    protected function getParams(): array
    {
        $params = [
            'timeout' => [
                'label' => 'Maximum Seconds Per Job',
                'value' => 0,
            ],
            'sleep' => [
                'label'    => 'Rest Seconds When Empty',
                'value'    => 60,
                'required' => true,
            ],
            'processes' => [
                'label' => 'Number of Processes',
                'value' => 1,
            ],
            'stopwaitsecs' => [
                'label' => 'Graceful Shutdown Seconds',
                'value' => 10,
            ],
            'daemon' => [
                'label' => 'Run Worker As Daemon',
                'value' => false,
            ],
            'force' => [
                'label' => 'Always Run, Even In Maintenance Mode',
                'value' => false,
            ],
            'tries' => [
                'label' => 'Maximum Tries',
                'value' => null,
            ],
        ];

        Console::table(
            ['Option', 'Value'],
            collect($params)->map(function ($item) {
                $value = $item['value'];

                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_null($value)) {
                    $value = '-';
                }

                return [$item['label'], $value];
            })->toArray(),
        );

        if (Console::confirm('Defaults look ok?', true)) {
            return $params;
        }

        foreach ($params as $key => $item) {
            if (is_bool($item['value'])) {
                $value = Console::confirm($item['label'], $item['value']);
            } else {
                $value = Console::askForNumber($item['label'], $item['value'], $item['required'] ?? false);
            }

            $params[$key]['value'] = $value;
        }

        return $params;
    }
}
