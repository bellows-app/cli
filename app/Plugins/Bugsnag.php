<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;
use Illuminate\Support\Facades\Http;

abstract class Bugsnag extends BasePlugin
{
    protected $organization;

    public function setupClient(): void
    {
        // TODO: Auto config (prompt for token and save if we don't have one) + point them where to get it
        // TODO: Offer to use existing projects if they want
        Http::macro('bugsnag', function () {
            return Http::baseUrl('https://api.bugsnag.com/')
                ->withToken(env('BUGSNAG_PERSONAL_ACCESS_TOKEN'), 'token')
                ->acceptJson()
                ->asJson();
        });

        $this->organization = Http::bugsnag()->get('user/organizations')->json()[0];
    }

    protected function createProject(string $type): array
    {
        return Http::bugsnag()->post("organizations/{$this->organization['id']}/projects", [
            'name' => $this->config->appName,
            'type' => $type,
        ])->json();
    }
}
