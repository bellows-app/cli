<?php

namespace App\Plugins;

class BugsnagPHP extends Bugsnag
{
    protected bool $createProject = false;

    protected ?string $bugsnagKey;

    protected array $requiredComposerPackages = [
        'bugsnag/bugsnag-laravel',
    ];

    public function enabled(): bool
    {
        if (!$this->getDefaultEnabled()['enabled']) {
            $this->info('Bugsnag is not installed in this project, skipping.');

            return false;
        }

        return $this->confirm('Use Bugsnag?', $this->getDefaultEnabled()['enabled']);
    }

    public function setup($server): void
    {
        $this->bugsnagKey = $this->env->get('BUGSNAG_API_KEY');

        if ($this->bugsnagKey) {
            $this->info('Using existing Bugsnag PHP key from .env');
            return;
        }

        if (!$this->confirm('Create Bugsnag PHP Project?', true)) {
            return;
        }

        $this->setupClient();

        $project = $this->createProject('laravel');

        $this->bugsnagKey = $project['api_key'];

        // $bugsnagLaravelProject = $bugsnagProjects->first(fn ($r) => $r['type'] === 'laravel');
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        if (!$this->bugsnagKey) {
            return [];
        }

        return ['BUGSNAG_API_KEY' => $this->bugsnagKey];
    }
}
