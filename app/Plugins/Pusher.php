<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Plugin;
use Illuminate\Http\Client\PendingRequest;

class Pusher extends Plugin
{
    protected array $appConfig;

    protected array $requiredComposerPackages = [
        'pusher/pusher-php-server',
    ];

    public function setup(): void
    {
        $this->http->createJsonClient(
            'https://cli.pusher.com/',
            fn (PendingRequest $request, $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://dashboard.pusher.com/accounts/api_key',
                credentials: ['token'],
            ),
            fn (PendingRequest $request) => $request->get('apps.json'),
        );

        $appName = config('app.name');

        $this->console->info("Pusher API limitations don't allow {$appName} to create an app for you.");
        $this->console->info("If you'd like to create one head to <comment>https://dashboard.pusher.com/channels</comment> then refresh the list below.");

        $this->presentChoices();
    }

    protected function presentChoices()
    {
        $apps = collect($this->http->client()->get('apps.json')->json());

        $refreshLabel = 'Refresh App List';

        $appName = $this->console->choice('Which app do you want to use?', $apps->pluck('name')->concat([$refreshLabel])->toArray());

        if ($appName === $refreshLabel) {
            $this->presentChoices();

            return;
        }

        $app = $apps->first(fn ($app) => $app['name'] === $appName);

        $this->appConfig = $this->http->client()->get("apps/{$app['id']}/tokens.json")->json()[0];

        $this->appConfig['cluster'] = $app['cluster'];
    }

    public function setEnvironmentVariables(): array
    {
        return [
            'BROADCAST_DRIVER'   => 'pusher',
            'PUSHER_APP_ID'      => $this->appConfig['app_id'],
            'PUSHER_APP_KEY'     => $this->appConfig['key'],
            'PUSHER_APP_SECRET'  => $this->appConfig['secret'],
            'PUSHER_APP_CLUSTER' => $this->appConfig['cluster'],
        ];
    }
}
