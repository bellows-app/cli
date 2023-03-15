<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\Worker;
use Bellows\Plugin;

class QueueWorker extends Plugin
{
    protected $queueWorkers = [];

    public function enabled(): bool
    {
        return $this->console->confirm('Do you want to set up any queue workers?');
    }

    public function setup(): void
    {
        $addAnother = true;

        $localConnection = $this->localEnv->get('QUEUE_CONNECTION', 'database');

        if ($localConnection === 'sync') {
            $localConnection = null;
        }

        do {
            $connection = $this->console->askRequired('Connection', $localConnection);
            $queue = $this->console->ask('Queue', 'default');

            $params = [
                'timeout' => [
                    'label' => 'Maximum Seconds Per Job',
                    'value' => 0,
                ],
                'sleep' => [
                    'label' => 'Rest Seconds When Empty',
                    'value' => 60,
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

            $this->console->table(
                ['Option', 'Value'],
                collect($params)->map(function ($item) {
                    $value = $item['value'];

                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    } else if (is_null($value)) {
                        $value = '-';
                    }

                    return [$item['label'], $value];
                })->toArray(),
            );

            if (!$this->console->confirm('Defaults look ok?', true)) {
                foreach ($params as $key => $item) {
                    if (is_bool($item['value'])) {
                        $value = $this->console->confirm($item['label'], $item['value']);
                    } else {
                        $value = $this->console->askForNumber($item['label'], $item['value'], $item['required'] ?? false);
                    }

                    $params[$key]['value'] = $value;
                }
            }

            $worker = array_merge([
                'connection' => $connection,
                'queue' => $queue,
            ], collect($params)->mapWithKeys(function ($item, $key) {
                return [$key => $item['value']];
            })->toArray());

            $this->queueWorkers[] = Worker::from($worker);

            $addAnother = $this->console->confirm('Do you want to add another queue worker?');
        } while ($addAnother);
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
