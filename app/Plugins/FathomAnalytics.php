<?php

namespace App\Plugins;

use App\DeployMate\Data\NewTokenPrompt;
use App\DeployMate\Plugin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FathomAnalytics extends Plugin
{
    protected $siteId;

    public function enabled(): bool
    {
        return $this->confirm(
            'Do you want to enable Fathom Analytics?',
            !Str::contains($this->projectConfig->domain, ['dev.', 'staging.'])
        );
    }

    public function setup($server): void
    {
        $token = $this->askForToken(
            newTokenPrompt: new NewTokenPrompt(
                url: 'https://app.usefathom.com/api',
            )
        );

        Http::macro(
            'fathom',
            fn () =>  Http::baseUrl('https://api.usefathom.com/v1/')
                ->withToken($token)
                ->acceptJson()
                ->asJson()
        );

        if ($this->confirm('Create new Fathom Analytics site?', true)) {
            $siteName = $this->ask('Enter your site name', $this->projectConfig->appName);

            $response = Http::fathom()->post('sites', [
                'name' => $siteName,
            ])->json();

            $this->siteId = $response['id'];

            return;
        }

        $sites = collect(
            Http::fathom()->get('sites', ['limit' => 100])->json()['data']
        );

        $siteChoices = $sites->sortBy('name')->mapWithKeys(fn ($site) => [$site['id'] => $site['name']]);

        $default = $sites->first(fn ($site) => $site['name'] === $this->projectConfig->appName);

        $this->siteId = $this->choice('Choose a site', $siteChoices->toArray(), $default['id'] ?? null);
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'FATHOM_SITE_ID' => $this->siteId,
        ];
    }
}
