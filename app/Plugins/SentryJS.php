<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Npm;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class SentryJS extends Sentry implements Launchable, Deployable, Installable
{
    use CanBeInstalled;

    protected string $jsFramework = 'js';

    protected array $anyRequiredNpmPackages = [
        '@sentry/vue',
        '@sentry/react',
    ];

    public function install(): void
    {
        $jsFramework = Console::choice('Which JS framework are you using?', ['Vue', 'React', 'Neither']);

        $this->jsFramework = match ($jsFramework) {
            'Vue'   => 'vue',
            'React' => 'react',
            default => 'js',
        };

        if (Console::confirm('Setup Sentry JS project now?', false)) {
            $this->launch();
        }
    }

    public function launch(): void
    {
        $this->sentryClientKey = Project::env()->get('SENTRY_JS_DSN');

        if ($this->sentryClientKey) {
            Console::miniTask('Using existing Sentry JS client key from', '.env');

            return;
        }

        $this->setupSentry();
    }

    public function deploy(): bool
    {
        $this->launch();

        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->hasAll('SENTRY_JS_DSN', 'VITE_SENTRY_JS_DSN');
    }

    public function npmPackagesToInstall(): array
    {
        return match ($this->jsFramework) {
            'vue' => [
                '@sentry/vue',
            ],
            'react' => [
                '@sentry/react',
            ],
            default => [],
        };
    }

    public function installWrapUp(): void
    {
        if ($this->jsFramework === 'js') {
            return;
        }

        // TODO: This seems tricky with Inertia... not sure what to do just yet.
        // $appJs = Project::file('resources/js/app.ts')
        //     ->addJsImport("import * as Sentry from '@sentry/vue'")
        //     ->addAfterJsImports(<<<JS
        //     Sentry.init({
        //         app,
        //         dsn: "https://f4d3a7ce240d46d5802bcbdfac3d8b90@o4505183458361344.ingest.sentry.io/4505245867376640",
        //         integrations: [
        //             new Sentry.BrowserTracing({
        //             routingInstrumentation: Sentry.vueRouterInstrumentation(router),
        //             }),
        //             new Sentry.Replay(),
        //         ],
        //         // Performance Monitoring
        //         tracesSampleRate: 1.0, // Capture 100% of the transactions, reduce in production!
        //         // Session Replay
        //         replaysSessionSampleRate: 0.1, // This sets the sample rate at 10%. You may want to change it to 100% while in development and then sample at a lower rate in production.
        //         replaysOnErrorSampleRate: 1.0, // If you're not already sampling the entire session, change the sample rate to 100% when sampling sessions where errors occur.
        //     });
        //     JS);

        // // TODO: What does react need as far as setup? Check out a bare Jetstream project
        // // Also is this all too specific to me? To Jetstream?
        // // https://docs.bugsnag.com/platforms/javascript/react/
        // if ($this->jsFramework === 'vue') {
        //     $appJs->replace(
        //         '.use(plugin)',
        //         // TODO: Double check that this is an ok fallback
        //         ".use(plugin)\n.use(typeof bugsnagVue !== 'undefined' ? bugsnagVue : () => {})"
        //     );
        // }
    }

    public function environmentVariables(): array
    {
        if (!$this->sentryClientKey) {
            return [];
        }

        $params = [
            'SENTRY_JS_DSN'      => $this->sentryClientKey,
            'VITE_SENTRY_JS_DSN' => '${SENTRY_JS_DSN}',
        ];

        if ($this->tracesSampleRate !== null) {
            $params['SENTRY_JS_TRACES_SAMPLE_RATE'] = $this->tracesSampleRate;
            $params['VITE_SENTRY_JS_TRACES_SAMPLE_RATE'] = '${SENTRY_JS_TRACES_SAMPLE_RATE}';
        }

        return $params;
    }

    protected function getProjectType(): string
    {
        foreach ($this->anyRequiredNpmPackages as $package) {
            if (Npm::packageIsInstalled($package)) {
                return 'javascript-' . collect(explode('/', $package))->last();
            }
        }

        // We're never going to hit this, but it's a sensible default
        return 'javascript';
    }
}
