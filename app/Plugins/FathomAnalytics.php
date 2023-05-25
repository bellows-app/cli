<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Http;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Http\Client\PendingRequest;

class FathomAnalytics extends Plugin implements Launchable, Deployable, Installable
{
    use CanBeInstalled;

    protected $siteId;

    public function __construct(
        protected Http $http,
    ) {
    }

    public function enabled(): bool
    {
        return Console::confirm(
            'Do you want to enable Fathom Analytics?',
        );
    }

    public function launch(): void
    {
        $this->http->createJsonClient(
            'https://api.usefathom.com/v1/',
            fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://app.usefathom.com/api',
                credentials: ['token'],
                displayName: 'Fathom Analytics',
            ),
            fn (PendingRequest $request) => $request->get('sites', ['limit' => 1]),
        );

        if (Console::confirm('Create new Fathom Analytics site?', true)) {
            $siteName = Console::ask('Enter your site name', Project::config()->appName);

            $response = $this->http->client()->post('sites', [
                'name' => $siteName,
            ])->json();

            $this->siteId = $response['id'];

            return;
        }

        $sites = collect(
            $this->http->client()->get('sites', ['limit' => 100])->json()['data']
        );

        $this->siteId = Console::choiceFromCollection(
            'Choose a site',
            $sites,
            'name',
            Project::config()->appName,
        )['id'];
    }

    public function deploy(): bool
    {
        $this->launch();

        return true;
    }

    public function install(): void
    {
        if (Console::confirm('Setup Fathom Analytics now?', false)) {
            $this->launch();
        }
    }

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->has('FATHOM_SITE_ID');
    }

    public function updateConfig(): array
    {
        // TODO: Also add chunk into main app layout file to check for this
        // (maybe it's a separate blade component that we can just include?)
        return [
            'services.fathom_analytics.site_id' => "env('FATHOM_SITE_ID')",
        ];
    }

    public function installWrapUp(): void
    {
        // TODO: What does a non-Jetstream install look like? Are these files at all correct?
        Project::file('resources/views/app.blade.php')->replace(
            '</head>',
            "    @include('partials.fathom_analytics')\n</head>",
        );

        Project::writeFile(
            'resources/views/partials/fathom_analytics.blade.php',
            <<<'HTML'
            @if (config('services.fathom_analytics.site_id'))
                <script src="https://cdn.usefathom.com/script.js" data-site="{{ config('services.fathom_analytics.site_id') }}" defer></script>
            @endif
            HTML
        );
    }

    public function environmentVariables(): array
    {
        if (!isset($this->siteId)) {
            return [];
        }

        return [
            'FATHOM_SITE_ID' => $this->siteId,
        ];
    }
}
