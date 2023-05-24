<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Composer;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class BugsnagPHP extends Bugsnag implements Launchable, Deployable, Installable
{
    use CanBeInstalled;

    protected array $anyRequiredComposerPackages = [
        'bugsnag/bugsnag-laravel',
        'bugsnag/bugsnag',
    ];

    public function install(): void
    {
        // TODO: Offer to handle account setup now if they want
    }

    public function launch(): void
    {
        $this->bugsnagKey = Project::env()->get('BUGSNAG_API_KEY');

        if ($this->bugsnagKey) {
            Console::miniTask('Using existing Bugsnag PHP key from', '.env');

            return;
        }

        $type = Composer::packageIsInstalled('laravel/framework') ? 'laravel' : 'php';

        $this->setupClient();

        if (Console::confirm('Create Bugsnag PHP project?', true)) {
            $project = $this->createProject($type);
            $this->bugsnagKey = $project['api_key'];

            return;
        }

        // TODO: Provide a way to bail if need be
        $project = $this->selectFromExistingProjects($type);
        $this->bugsnagKey = $project['api_key'];
    }

    public function deploy(): bool
    {
        $this->launch();

        return true;
    }

    public function providersToRegister(): array
    {
        return [
            'Bugsnag\BugsnagLaravel\BugsnagServiceProvider::class',
        ];
    }

    public function aliasesToRegister(): array
    {
        return [
            'Bugsnag' => 'Bugsnag\BugsnagLaravel\Facades\Bugsnag::class',
        ];
    }

    public function updateConfig(): array
    {
        return [
            'logging.channels.stack.driver'   => 'stack',
            'logging.channels.stack.channels' => "['single', 'bugsnag']",
            'logging.channels.bugsnag.driver' => 'bugsnag',
        ];
    }

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->has('BUGSNAG_API_KEY');
    }

    public function environmentVariables(): array
    {
        if (!isset($this->bugsnagKey)) {
            return [];
        }

        return [
            'BUGSNAG_API_KEY'               => $this->bugsnagKey,
            'BUGSNAG_NOTIFY_RELEASE_STAGES' => 'production,development,staging',
        ];
    }
}
