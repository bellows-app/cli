<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FathomAnalytics extends BasePlugin
{
    protected $siteId;

    public function enabled(): bool
    {
        return $this->confirm(
            'Do you want to enable Fathom Analytics?',
            !Str::contains($this->config->domain, ['dev.', 'staging.'])
        );
    }

    public function setup($server): void
    {
        // if (!env('FATHOM_ANALYTICS_TOKEN')) {
        //     $this->info(
        //         'FATHOM_ANALYTICS_TOKEN to your .env file. Get a token here: https://app.usefathom.com/api'
        //     );

        //     $token = $this->ask('Enter your token');

        //     $this->config->setEnvironmentVariable('FATHOM_ANALYTICS_TOKEN', $token);
        //     return;
        // }

        Http::macro('fathom', function () {
            return Http::baseUrl('https://api.usefathom.com/v1/')
                ->withToken(env('FATHOM_ANALYTICS_TOKEN'))
                ->acceptJson()
                ->asJson();
        });

        if ($this->confirm('Create new Fathom Analytics site?', true)) {
            $siteName = $this->ask('Enter your site name', $this->config->appName);

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

        $default = $sites->first(fn ($site) => $site['name'] === $this->config->appName);

        $this->siteId = $this->choice('Choose a site', $siteChoices->toArray(), $default['id'] ?? null);
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'FATHOM_SITE_ID' => $this->siteId,
        ];
    }
}
