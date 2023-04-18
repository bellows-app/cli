<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Facades\Console;
use Bellows\Http;
use Bellows\Plugin;
use Bellows\Project;
use Illuminate\Http\Client\PendingRequest;

class FathomAnalytics extends Plugin
{
    protected $siteId;

    public function __construct(
        protected Http $http,
        protected Project $project,
    ) {
    }

    public function enabled(): bool
    {
        return Console::confirm(
            'Do you want to enable Fathom Analytics?',
        );
    }

    public function setup(): void
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
            $siteName = Console::ask('Enter your site name', $this->project->config->appName);

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
            $this->project->config->appName,
        )['id'];
    }

    public function environmentVariables(): array
    {
        return [
            'FATHOM_SITE_ID' => $this->siteId,
        ];
    }
}
