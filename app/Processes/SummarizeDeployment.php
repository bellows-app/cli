<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Closure;

class SummarizeDeployment
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        if (count($deployment->summary) === 0) {
            return $next($deployment);
        }

        Console::step('Summary');

        Console::table(
            ['Task', 'Value'],
            collect($deployment->summary)->map(fn ($row) => [
                "<comment>{$row[0]}</comment>",
                $row[1] . PHP_EOL,
            ])->toArray(),
        );

        return $next($deployment);
    }
}
