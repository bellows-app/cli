<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Project;
use Closure;

class SetLaunchEnvironmentVariables
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        Console::step('Environment Variables');

        $siteEnv = Deployment::site()->env();

        $updatedVars = collect($deployment->manager->environmentVariables([
            'APP_NAME'      => Project::appName(),
            'APP_URL'       => (Project::siteIsSecure() ? 'https://' : 'http://') . Project::domain(),
            'VITE_APP_ENV'  => '${APP_ENV}',
            'VITE_APP_NAME' => '${APP_NAME}',
        ]));

        $updatedVars->each(
            fn ($v, $k) => $siteEnv->set(
                $k,
                is_array($v) ? $v[0] : $v,
                is_array($v),
            )
        );

        $inLocalButNotInRemote = collect(
            array_keys(Project::env()->all())
        )->diff(array_keys($siteEnv->all()))->values();

        if ($inLocalButNotInRemote->isNotEmpty()) {
            Console::newLine();
            Console::info('The following environment variables are in your local .env file but not in your remote .env file:');
            Console::newLine();

            $inLocalButNotInRemote->each(
                fn ($k) => Console::comment($k)
            );

            if (Console::confirm('Would you like to add any of them? They will be added with their existing values.')) {
                $toAdd = Console::choice(
                    question: 'Which environment variables would you like to add?',
                    choices: $inLocalButNotInRemote->toArray(),
                    multiple: true,
                );

                collect($toAdd)->each(
                    fn ($k) => $siteEnv->set($k, Project::env()->get($k))
                );
            }
        }

        Console::withSpinner(
            title: 'Updating',
            task: fn () => Deployment::site()->updateEnv((string) $siteEnv),
        );

        $deployment->summary[] = ['Environment Variables', $updatedVars->keys()->join(PHP_EOL)];

        return $next($deployment);
    }
}
