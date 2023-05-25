<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Http;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Bellows\Util\DeployHelper;
use Illuminate\Http\Client\PendingRequest;

class Ably extends Plugin implements Launchable, Deployable, Installable
{
    use CanBeInstalled;

    protected const BROADCAST_DRIVER = 'ably';

    protected string $key;

    protected array $requiredComposerPackages = [
        'ably/ably-php',
    ];

    public function __construct(
        protected Http $http,
    ) {
    }

    public function launch(): void
    {
        $this->http->createJsonClient(
            'https://control.ably.net/v1/',
            fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://ably.com/users/access_tokens',
                helpText: 'When creating a token, make sure to select the following permissions: read:app, write:app, read:key',
                credentials: ['token'],
                displayName: 'Ably',
            ),
            fn (PendingRequest $request) => $request->get('me'),
        );

        $me = $this->http->client()->get('me')->json();

        $accountId = $me['account']['id'];

        if (Console::confirm('Create new app?', true)) {
            $appName = Console::ask('App name', Project::config()->appName);

            $app = $this->http->client()->post("accounts/{$accountId}/apps", [
                'name' => $appName,
            ])->json();
        } else {
            $apps = collect($this->http->client()->get("accounts/{$accountId}/apps")->json());

            $app = Console::choiceFromCollection(
                'Which app do you want to use?',
                $apps,
                'name',
                Project::config()->appName,
            );
        }

        $keys = collect($this->http->client()->get("apps/{$app['id']}/keys")->json());

        $this->key = Console::choiceFromCollection(
            'Which key do you want to use?',
            $keys,
            'name',
            Project::config()->appName,
        )['key'];
    }

    public function deploy(): bool
    {
        if (
            !DeployHelper::wantsToChangeValueTo(
                $this->site->getEnv()->get('BROADCAST_DRIVER'),
                self::BROADCAST_DRIVER,
                'Change broadcast driver to Ably'
            )
        ) {
            return false;
        }

        $this->launch();

        return true;
    }

    public function environmentVariables(): array
    {
        return [
            'BROADCAST_DRIVER' => self::BROADCAST_DRIVER,
            'ABLY_KEY'         => $this->key,
        ];
    }

    public function canDeploy(): bool
    {
        return $this->site->getEnv()->get('BROADCAST_DRIVER') !== self::BROADCAST_DRIVER
            || !$this->site->getEnv()->has('ABLY_KEY');
    }
}
