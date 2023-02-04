<?php

namespace App\Plugins;

use App\DeployMate\Data\NewTokenPrompt;
use App\DeployMate\Plugin;
use Illuminate\Support\Facades\Http;

abstract class Bugsnag extends Plugin
{
    public function setupClient(): void
    {
        $token = $this->askForToken(
            newTokenPrompt: new NewTokenPrompt(
                url: 'https://app.bugsnag.com/settings/my-account',
            )
        );

        Http::macro(
            'bugsnag',
            fn () => Http::baseUrl('https://api.bugsnag.com/')
                ->withToken($token, 'token')
                ->acceptJson()
                ->asJson()
        );

        $organization = Http::bugsnag()->get('user/organizations')->json()[0];

        Http::macro(
            'bugsnagOrganization',
            fn () => Http::bugsnag()->baseUrl("https://api.bugsnag.com/organizations/{$organization['id']}")
        );
    }

    protected function createProject(string $type): array
    {
        return Http::bugsnagOrganization()->post('projects', [
            'name' => $this->projectConfig->appName,
            'type' => $type,
        ])->json();
    }

    protected function selectFromExistingProjects(string $type): array
    {
        $result = Http::bugsnagOrganization()->get('projects', [
            'per_page' => 100,
        ])->json();

        $result = collect($result)->filter(fn ($project) => $project['type'] === $type)->values();

        $choices = $result->sortBy('name')->mapWithKeys(fn ($project) => [
            $project['id'] => $project['name'],
        ])->toArray();

        $choice = $this->choice(
            'Select a project',
            $choices,
            $result->pluck('name')->contains($this->projectConfig->appName)
                ? $this->projectConfig->appName : null
        );

        return $result->first(fn ($project) => $project['id'] === $choice);
    }
}
