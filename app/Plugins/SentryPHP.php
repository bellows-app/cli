<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;

class SentryPHP extends Sentry
{
    protected array $anyRequiredComposerPackages = [
        'sentry/sentry-laravel',
    ];

    public function setup(): void
    {
        $this->sentryClientKey = Project::env()->get('SENTRY_LARAVEL_DSN');

        if ($this->sentryClientKey) {
            Console::miniTask('Using existing Sentry Laravel client key from', '.env');

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
            'SENTRY_LARAVEL_DSN' => $this->sentryClientKey,
        ];

        if ($this->tracesSampleRate !== null) {
            $params['SENTRY_TRACES_SAMPLE_RATE'] = $this->tracesSampleRate;
        }

        return $params;
    }

    protected function getProjectType(): string
    {
        return 'php-laravel';
    }
}
