<?php

namespace App\Plugins;

use App\Bellows\Data\AddApiCredentialsPrompt;
use App\Bellows\Plugin;
use Illuminate\Http\Client\PendingRequest;

class Pusher extends Plugin
{
    protected array $appConfig;

    protected array $requiredComposerPackages = [
        'pusher/pusher-php-server',
    ];

    public function setup($server): void
    {
        $this->http->createJsonClient(
            'https://cli.pusher.com/',
            fn (PendingRequest $request, $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://dashboard.pusher.com/accounts/api_key',
                credentials: ['token'],
            ),
        );

        $apps = collect($this->http->client()->get('apps.json')->json());

        $appName = $this->console->choice('Which app do you want to use?', $apps->pluck('name')->toArray());

        $app = $apps->first(fn ($app) => $app['name'] === $appName);

        $this->appConfig = $this->http->client()->get("apps/{$app['id']}/tokens.json")->json()[0];

        $this->appConfig['cluster'] = $app['cluster'];

        // TODO: Notify them that we cannot currently create an app, give them a direct link to create it, and refresh list option?
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'PUSHER_APP_ID'      => $this->appConfig['app_id'],
            'PUSHER_APP_KEY'     => $this->appConfig['key'],
            'PUSHER_APP_SECRET'  => $this->appConfig['secret'],
            'PUSHER_APP_CLUSTER' => $this->appConfig['cluster'],
        ];
    }
}
