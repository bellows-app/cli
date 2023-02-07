<?php

namespace App\Plugins;

class BugsnagPHP extends Bugsnag
{
    protected bool $createProject = false;

    protected ?string $bugsnagKey;

    protected array $requiredComposerPackages = [
        'bugsnag/bugsnag-laravel',
    ];

    public function setup($server): void
    {
        $this->bugsnagKey = $this->env->get('BUGSNAG_API_KEY');

        if ($this->bugsnagKey) {
            $this->console->info('Using existing Bugsnag PHP key from .env');
            return;
        }

        $type = $this->composer->packageIsInstalled('laravel/framework') ? 'laravel' : 'php';

        $this->setupClient();

        if ($this->console->confirm('Create Bugsnag PHP Project?', true)) {
            $project = $this->createProject($type);
            $this->bugsnagKey = $project['api_key'];
        }

        if ($this->console->confirm('Use existing Bugsnag PHP Project?', true)) {
            $project = $this->selectFromExistingProjects($type);
            $this->bugsnagKey = $project['api_key'];
        }
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        if (!$this->bugsnagKey) {
            return [];
        }

        return ['BUGSNAG_API_KEY' => $this->bugsnagKey];
    }
}
