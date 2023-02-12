<?php

namespace App\Plugins;

use App\Bellows\Data\AddApiCredentialsPrompt;
use App\Bellows\Plugin;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

class FathomAnalytics extends Plugin
{
    protected $siteId;

    public function enabled(): bool
    {
        return $this->console->confirm(
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
            )
        );

        if ($this->console->confirm('Create new Fathom Analytics site?', true)) {
            $siteName = $this->console->ask('Enter your site name', $this->projectConfig->appName);

            $response = $this->http->client()->post('sites', [
                'name' => $siteName,
            ])->json();

            $this->siteId = $response['id'];

            return;
        }

        $sites = collect(
            $this->http->client()->get('sites', ['limit' => 100])->json()['data']
        );

        $this->siteId = $this->console->choiceFromCollection(
            'Choose a site',
            $sites,
            'name',
            $this->projectConfig->appName,
        )['id'];
    }

    public function setEnvironmentVariables(array $envVars): array
    {
        return [
            'FATHOM_SITE_ID' => $this->siteId,
        ];
    }
}
