<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Composer;

class BugsnagPHP extends Bugsnag
{
    protected bool $createProject = false;

    protected ?string $bugsnagKey;

    protected array $anyRequiredComposerPackages = [
        'bugsnag/bugsnag-laravel',
        'bugsnag/bugsnag',
    ];

    public function setup(): void
    {
        $this->bugsnagKey = Project::env()->get('BUGSNAG_API_KEY');

        if ($this->bugsnagKey) {
            Console::miniTask('Using existing Bugsnag PHP key from', '.env');

            return;
        }

        $type = Composer::packageIsInstalled('laravel/framework') ? 'laravel' : 'php';

        $this->setupClient();

        if (Console::confirm('Create Bugsnag PHP Project?', true)) {
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

        return ['BUGSNAG_API_KEY' => $this->bugsnagKey];
    }
}
