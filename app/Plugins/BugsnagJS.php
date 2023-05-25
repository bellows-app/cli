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

    protected string $jsFramework = 'js';

    public function install(): void
    {
        $jsFramework = Console::choice('Which JS framework are you using?', ['Vue', 'React', 'Neither']);

        $this->jsFramework = match ($jsFramework) {
            'Vue' => 'vue',
            'React' => 'react',
            default => 'js',
        };

        if (Console::confirm('Setup Bugsnag JS project now?', false)) {
            $this->launch();
        }
    }

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

        $project = $this->selectFromExistingProjects($type);

        $this->bugsnagKey = $project['api_key'];
    }

    public function npmPackagesToInstall(): array
    {
        return array_merge(
            $this->requiredNpmPackages,
            match ($this->jsFramework) {
                'vue' => [
                    '@bugsnag/plugin-vue',
                ],
                'react' => [
                    '@bugsnag/plugin-react',
                ],
                default => [],
            }
        );
    }

    public function installWrapUp(): void
    {
        if ($this->jsFramework === 'js') {
            return;
        }


        $pluginName = ucwords($this->jsFramework);

        $appJs = Project::file('resources/js/app.ts')
            ->addJsImport([
                "import Bugsnag from '@bugsnag/js'",
                "import BugsnagPlugin{$pluginName} from '@bugsnag/plugin-{$this->jsFramework}'",
            ])
            ->addAfterJsImports(<<<JS
if (import.meta.env.VITE_BUGSNAG_JS_API_KEY) {
    Bugsnag.start({
        apiKey: import.meta.env.VITE_BUGSNAG_JS_API_KEY,
        plugins: [new BugsnagPlugin$pluginName()],
        releaseStage: import.meta.env.VITE_APP_ENV,
        enabledReleaseStages: ['development', 'production'],
    });
}

const bugsnagVue = import.meta.env.VITE_BUGSNAG_VUE_PLUGIN ? Bugsnag.getPlugin('$this->jsFramework') : undefined;
JS);

        // TODO: What does react need as far as setup? Check out a bare Jetstream project
        // Also is this all too specific to me? To Jetstream?
        // https://docs.bugsnag.com/platforms/javascript/react/
        if ($this->jsFramework === 'vue') {
            $appJs->replace(
                '.use(plugin)',
                // TODO: Double check that this is an ok fallback
                ".use(plugin)\n.use(typeof bugsnagVue !== 'undefined' ? bugsnagVue : () => {})"
            );
        }
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
