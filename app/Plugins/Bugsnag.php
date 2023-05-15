<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Http;
use Bellows\Plugin;
use Illuminate\Http\Client\PendingRequest;

abstract class Bugsnag extends Plugin
{
    protected ?string $bugsnagKey;

    public function __construct(
        protected Http $http,
        protected Project $project,
    ) {
    }

    public function setupClient(): void
    {
        $this->http->createJsonClient(
            'https://api.bugsnag.com/',
            fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token'], 'token'),
            new AddApiCredentialsPrompt(
                url: 'https://app.bugsnag.com/settings/my-account',
                credentials: ['token'],
                displayName: 'Bugsnag',
            ),
            fn (PendingRequest $request) => $request->get('user/organizations', ['per_page' => 1]),
            true,
        );

        $organization = $this->http->client()->get('user/organizations')->json()[0];

        $this->http->extendClient(
            "https://api.bugsnag.com/organizations/{$organization['id']}",
            'bugsnagOrganization',
        );
    }

    protected function createProject(string $type): array
    {
        return $this->http->client('bugsnagOrganization')->post('projects', [
            'name' => Console::ask('Project name', Project::config()->appName),
            'type' => $type,
        ])->json();
    }

    protected function selectFromExistingProjects(string $type): array
    {
        $result = $this->http->client('bugsnagOrganization')->get('projects', [
            'per_page' => 100,
        ])->json();

        $result = collect($result)->filter(fn ($project) => $project['type'] === $type)->values();

        return Console::choiceFromCollection(
            'Select a Bugsnag project',
            $result->sortBy('name'),
            'name',
            Project::config()->appName,
        );
    }
}
