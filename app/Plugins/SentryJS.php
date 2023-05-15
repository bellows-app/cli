<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Npm;

class SentryJS extends Sentry
{
    protected array $anyRequiredNpmPackages = [
        '@sentry/vue',
        '@sentry/react',
    ];

    public function setup(): void
    {
        $this->sentryClientKey = Project::env()->get('SENTRY_JS_DSN');

        if ($this->sentryClientKey) {
            Console::miniTask('Using existing Sentry JS client key from', '.env');

            return;
        }

        $this->setupSentry();
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
