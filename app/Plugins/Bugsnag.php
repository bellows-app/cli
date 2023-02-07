<?php

namespace App\Plugins;

use App\DeployMate\Data\AddApiCredentialsPrompt;
use App\DeployMate\Plugin;

abstract class Bugsnag extends Plugin
{
    public function setupClient(): void
    {
        $this->http->createJsonClient(
            'https://api.bugsnag.com/',
            fn ($request, $credentials) => $request->withToken($credentials['token'], 'token'),
            new AddApiCredentialsPrompt(
                url: 'https://app.bugsnag.com/settings/my-account',
                credentials: ['token'],
            ),
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
            'name' => $this->projectConfig->appName,
            'type' => $type,
        ])->json();
    }

    protected function selectFromExistingProjects(string $type): array
    {
        $result = $this->http->client('bugsnagOrganization')->get('projects', [
            'per_page' => 100,
        ])->json();

        $result = collect($result)->filter(fn ($project) => $project['type'] === $type)->values();

        $choices = $result->sortBy('name')->mapWithKeys(fn ($project) => [
            $project['id'] => $project['name'],
        ])->toArray();

        $choice = $this->console->choice(
            'Select a project',
            $choices,
            $result->pluck('name')->contains($this->projectConfig->appName)
                ? $this->projectConfig->appName : null
        );

        return $result->first(fn ($project) => $project['id'] === $choice);
    }
}
