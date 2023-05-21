<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Npm;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class BugsnagJS extends Bugsnag implements Launchable, Deployable, Installable
{
    use CanBeInstalled;

    protected array $requiredNpmPackages = [
        '@bugsnag/js',
    ];

    public function launch(): void
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

    public function npmPackagesToInstall(): array
    {
        return [
            // TODO: Can we determine if this is correct?
            // '@bugsnag/plugin-vue',
        ];
    }

    public function deploy(): bool
    {
        $this->launch();

        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->hasAll('BUGSNAG_JS_API_KEY', 'VITE_BUGSNAG_JS_API_KEY');
    }

    public function environmentVariables(): array
    {
        if (!isset($this->bugsnagKey)) {
            return [];
        }

        return [
            'BUGSNAG_JS_API_KEY'      => $this->bugsnagKey,
            'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
        ];
    }
}
