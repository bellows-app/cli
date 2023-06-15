<?php

namespace Bellows\Processes;

use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Closure;

class SetLocalEnvironmentVariables
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('Environment Variables');

        collect(
            array_merge(
                $installation->manager->environmentVariables([
                    'APP_NAME'      => Project::appName(),
                    'APP_URL'       => 'http://' . Project::domain(),
                    'VITE_APP_ENV'  => '${APP_ENV}',
                    'VITE_APP_NAME' => '${APP_NAME}',
                ]),
                $installation->config->get('env', []),
            )
        )->each(fn ($value, $key) => Project::env()->set($key, $value));

        Project::file('.env')->write((string) Project::env());

        return $next($installation);
    }
}
