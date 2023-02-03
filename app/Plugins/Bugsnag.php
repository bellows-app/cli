<?php

namespace App\Plugins;

use App\DeployMate\NewTokenPrompt;
use App\DeployMate\Plugin;
use Illuminate\Support\Facades\Http;

abstract class Bugsnag extends Plugin
{
    protected $organization;

    public function setupClient(): void
    {
        $token = $this->askForToken(
            newTokenPrompt: new NewTokenPrompt(
                url: 'https://app.bugsnag.com/settings/my-account',
            )
        );

        // TODO: Offer to use existing projects if they want
        Http::macro(
            'bugsnag',
            fn () => Http::baseUrl('https://api.bugsnag.com/')
                ->withToken($token, 'token')
                ->acceptJson()
                ->asJson()
        );

        $this->organization = Http::bugsnag()->get('user/organizations')->json()[0];
    }

    protected function createProject(string $type): array
    {
        return Http::bugsnag()->post("organizations/{$this->organization['id']}/projects", [
            'name' => $this->projectConfig->appName,
            'type' => $type,
        ])->json();
    }
}
