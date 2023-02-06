<?php

namespace App\Plugins;

use App\DeployMate\Data\AddApiCredentialsPrompt;
use App\DeployMate\Plugin;
use Illuminate\Http\Client\PendingRequest;
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
        $this->http->createJsonClient(
            'https://api.usefathom.com/v1/',
            fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://app.usefathom.com/api',
                credentials: ['token'],
            )
        );

        if ($this->confirm('Create new Fathom Analytics site?', true)) {
            $siteName = $this->ask('Enter your site name', $this->projectConfig->appName);

            $response = $this->http->client()->post('sites', [
                'name' => $siteName,
            ])->json();

            $this->siteId = $response['id'];

            return;
        }

        $sites = collect(
            $this->http->client()->get('sites', ['limit' => 100])->json()['data']
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
