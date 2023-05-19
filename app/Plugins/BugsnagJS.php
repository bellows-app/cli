<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Npm;

class BugsnagJS extends Bugsnag
{
    protected array $requiredNpmPackages = [
        '@bugsnag/js',
    ];

    public function setup(): void
    {
        $this->bugsnagKey = Project::env()->get('BUGSNAG_JS_API_KEY');

        if ($this->bugsnagKey) {
            Console::miniTask('Using existing Bugsnag JS key from', '.env');

            return;
        }

        $type = collect(['vue', 'react'])->first(fn ($p) => Npm::packageIsInstalled($p)) ?: 'js';

        $this->setupClient();

        if (Console::confirm('Create Bugsnag JS project?', true)) {
            $project = $this->createProject($type);
            $this->bugsnagKey = $project['api_key'];

            return;
        }

        // TODO: Provide a way to bail if need be
        $project = $this->selectFromExistingProjects($type);
        $this->bugsnagKey = $project['api_key'];
    }

    public function environmentVariables(): array
    {
        if (!$this->bugsnagKey) {
            return [];
        }

        return [
            'BUGSNAG_JS_API_KEY'      => $this->bugsnagKey,
            'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
        ];
    }

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->hasAll('BUGSNAG_JS_API_KEY', 'VITE_BUGSNAG_JS_API_KEY');
    }
}
