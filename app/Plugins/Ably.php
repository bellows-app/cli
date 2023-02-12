<?php

namespace App\Plugins;

use App\Bellows\Data\AddApiCredentialsPrompt;
use App\Bellows\Plugin;
use Illuminate\Http\Client\PendingRequest;

class Ably extends Plugin
{
    protected string $key;

    protected array $requiredComposerPackages = [
        'ably/ably-php',
    ];

    public function setup($server): void
    {
        $this->http->createJsonClient(
            'https://control.ably.net/v1/',
            fn (PendingRequest $request, $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://ably.com/users/access_tokens',
                helpText: 'When creating a token, make sure to select the following permissions: read:app, write:app, read:key',
                credentials: ['token'],
            ),
        );

        $me = $this->http->client()->get('me')->json();

        $accountId = $me['account']['id'];

        if ($this->console->confirm('Create new app?', true)) {
            $appName = $this->console->ask('App name', $this->projectConfig->appName);

            $app = $this->http->client()->post("accounts/{$accountId}/apps", [
                'name' => $appName,
            ])->json();
        } else {
            $apps = collect($this->http->client()->get("accounts/{$accountId}/apps")->json());

            $app = $this->console->choiceFromCollection(
                'Which app do you want to use?',
                $apps,
                'name',
                $this->projectConfig->appName,
            );
        }

        $keys = collect($this->http->client()->get("apps/{$app['id']}/keys")->json());

        $this->key = $this->console->choiceFromCollection(
            'Which key do you want to use?',
            $keys,
            'name',
            $this->projectConfig->appName,
        )['key'];
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        if (!isset($this->key)) {
            return [];
        }

        return [
            'BROADCAST_DRIVER' => 'ably',
            'ABLY_KEY'         => $this->key,
        ];
    }
}
