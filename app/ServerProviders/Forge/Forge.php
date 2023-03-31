<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Config;
use Bellows\Console;
use Bellows\Data\ForgeServer;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\ServerProviderInterface;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class Forge implements ServerProviderInterface
{
    const API_URL = 'https://forge.laravel.com/api/v1';

    public function __construct(
        protected Console $console,
        protected Config $config,
    ) {
    }

    public function setCredentials(): void
    {
        $this->setupBaseClient($this->getToken());
    }

    public function getServer(): ?ServerInterface
    {
        $servers = collect(
            Http::forge()->get('servers')->json()['servers']
        )->filter(fn ($s) => ! $s['revoked'])->values();

        if ($servers->isEmpty()) {
            return null;
        }

        if ($servers->count() === 1) {
            $server = $servers->first();

            $this->console->info("Found only one server, auto-selecting: <comment>{$server['name']}</comment>");

            return $server;
        }

        $serverName = $this->console->choice(
            'Which server would you like to use?',
            $servers->pluck('name')->sort()->values()->toArray()
        );

        $server = ForgeServer::from($servers->first(fn ($s) => $s['name'] === $serverName));

        return new Server($server, $this->console);
    }

    protected function getToken(): string
    {
        $apiHost = parse_url(self::API_URL, PHP_URL_HOST);

        $apiConfigKey = 'apiCredentials.' . str_replace('.', '-', $apiHost) . '.default';

        $token = $this->config->get($apiConfigKey);

        if ($token) {
            if ($this->isValidToken($token)) {
                return $token;
            }

            $this->console->warn('Your saved Forge token is invalid!');
            $this->console->newLine();
        }

        $this->console->info('Looks like we need a Forge API token, you can get one here:');
        $this->console->comment('https://forge.laravel.com/user-profile/api');

        do {
            if (isset($isValid)) {
                $this->console->warn('Invalid token, please try again.');
            }

            $token = $this->console->secret('Forge API Token');

            $isValid = $this->isValidToken($token);
        } while (! $isValid);

        $this->config->set($apiConfigKey, $token);

        return $token;
    }

    protected function setupBaseClient(string $token)
    {
        $url = self::API_URL;

        Http::macro(
            'forge',
            fn () => Http::baseUrl($url)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->retry(
                    3,
                    100,
                    function (
                        Exception $exception,
                        PendingRequest $request
                    ) {
                        if ($exception instanceof RequestException && $exception->response->status() === 429) {
                            sleep($exception->response->header('retry-after') + 1);

                            return true;
                        }

                        return false;
                    }
                )
        );
    }

    protected function isValidToken($token): bool
    {
        try {
            Http::baseUrl(self::API_URL)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->get('user')
                ->throw();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
