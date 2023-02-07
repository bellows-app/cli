<?php

namespace App\Plugins;

class BugsnagJS extends Bugsnag
{
    protected bool $createProject = false;

    protected ?string $bugsnagKey;

    protected array $requiredNpmPackages = [
        '@bugsnag/js',
    ];

    public function setup($server): void
    {
        $this->bugsnagKey = $this->env->get('BUGSNAG_JS_API_KEY');

        if ($this->bugsnagKey) {
            $this->console->info('Using existing Bugsnag JS key from .env');
            return;
        }

        $type = collect(['vue', 'react'])->first(fn ($p) => $this->npm->packageIsInstalled($p)) ?: 'js';

        $this->setupClient();

        if ($this->console->confirm('Create Bugsnag JS Project?', true)) {
            $project = $this->createProject($type);
            $this->bugsnagKey = $project['api_key'];
        }

        if ($this->console->confirm('Use existing Bugsnag JS Project?', true)) {
            $project = $this->selectFromExistingProjects($type);
            $this->bugsnagKey = $project['api_key'];
        }

        return;
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        if (!$this->bugsnagKey) {
            return [];
        }

        return [
            'BUGSNAG_JS_API_KEY'      => $this->bugsnagKey,
            'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
        ];
    }
}
