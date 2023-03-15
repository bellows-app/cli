<?php

namespace Bellows\Plugins;

class BugsnagPHP extends Bugsnag
{
    protected bool $createProject = false;

    protected ?string $bugsnagKey;

    protected array $requiredComposerPackages = [
        'bugsnag/bugsnag-laravel',
    ];

    public function setup(): void
    {
        $this->bugsnagKey = $this->localEnv->get('BUGSNAG_API_KEY');

        if ($this->bugsnagKey) {
            $this->console->miniTask('Using existing Bugsnag PHP key from', '.env');
            return;
        }

        $type = $this->composer->packageIsInstalled('laravel/framework') ? 'laravel' : 'php';

        $this->setupClient();

        if ($this->console->confirm('Create Bugsnag PHP Project?', true)) {
            $project = $this->createProject($type);
            $this->bugsnagKey = $project['api_key'];
            return;
        }

        // TODO: Provide a way to bail if need be
        $project = $this->selectFromExistingProjects($type);
        $this->bugsnagKey = $project['api_key'];
    }

    public function setEnvironmentVariables(): array
    {
        if (!$this->bugsnagKey) {
            return [];
        }

        return ['BUGSNAG_API_KEY' => $this->bugsnagKey];
    }
}
