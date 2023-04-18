<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Data\PluginWorker;
use Bellows\DeployScript;
use Bellows\Facades\Console;
use Bellows\Plugin;
use Bellows\Project;

class QueueWorker extends Plugin
{
    protected $queueWorkers = [];

    public function __construct(
        protected Artisan $artisan,
        protected Project $project,
    ) {
    }

    public function enabled(): bool
    {
        return Console::confirm('Do you want to set up any queue workers?');
    }

    public function setup(): void
    {
        $addAnother = true;

        $localConnection = $this->project->env->get('QUEUE_CONNECTION', 'database');

        if ($localConnection === 'sync') {
            $localConnection = null;
        }

        do {
            $connection = Console::anticipateRequired(
                'Connection',
                ['database', 'sqs', 'redis', 'beanstalkd'],
                $localConnection
            );
            $queue = Console::ask('Queue', 'default');

            $params = $this->getParams();

            $worker = array_merge([
                'connection' => $connection,
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

    public function workers(): array
    {
        return $this->queueWorkers;
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addBeforePHPReload($deployScript, [
            $this->artisan->inDeployScript('queue:restart'),
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
                    $value = $value ? 'true' : 'false';
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
