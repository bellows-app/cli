<?php

namespace App\Plugins;

class BugsnagJS extends Bugsnag
{
    protected bool $createProject = false;

    protected ?string $bugsnagKey;

    protected array $requiredNpmPackages = [
        '@bugsnag/js',
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
        $this->bugsnagKey = $this->env->get('BUGSNAG_JS_API_KEY');

        if ($this->bugsnagKey) {
            $this->info('Using existing Bugsnag JS key from .env');
            return;
        }

        if (!$this->confirm('Create Bugsnag JS Project?', true)) {
            return;
        }

        $this->setupClient();

        $project = $this->createProject('vue');

        $this->bugsnagKey = $project['api_key'];

        // $bugsnagJsProject = $bugsnagProjects->first(fn ($r) => $r['type'] === 'vue');
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
