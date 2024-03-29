<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Config;
use Bellows\Contracts\ServerProviderServer;
use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Data\Server as ServerData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\ServerProviders\Forge\Config\LoadBalancer;
use Bellows\ServerProviders\Forge\Config\SingleServer;
use Bellows\ServerProviders\ServerDeployTarget;
use Bellows\ServerProviders\ServerProviderInterface;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Forge implements ServerProviderInterface
{
    public function __construct(
        protected Config $config,
    ) {
    }

    public function setCredentials(): void
    {
        Client::getInstance()->setToken($this->getToken());
    }

    public function getServers(): Collection
    {
        return collect(
            Client::getInstance()->http()->get('servers')->json()['servers']
        )->filter(fn ($s) => !$s['revoked'])->sortBy('name')->values();
    }

    public function getServer(): ?ServerProviderServer
    {
        $servers = $this->getServers();

        if ($servers->isEmpty()) {
            return null;
        }

        if ($servers->count() === 1) {
            $server = $servers->first();

            Console::info("Found only one server, auto-selecting: <comment>{$server['name']}</comment>");

            return new Server(ServerData::from($server));
        }

        $serverName = Console::choice(
            'Which server would you like to use?',
            $servers->pluck('name')->sort()->values()->toArray()
        );

        $server = ServerData::from($servers->first(fn ($s) => $s['name'] === $serverName));

        return new Server($server);
    }

    public function getServerDeployTargetFromServer(ServerInterface $server): ServerDeployTarget
    {
        if ($server->type === 'loadbalancer') {
            Console::miniTask('Detected', 'Load Balancer', true);
            Console::newLine();

            return new LoadBalancer($server);
        }

        return new SingleServer($server);
    }

    protected function getToken(): string
    {
        $apiHost = parse_url(Client::API_URL, PHP_URL_HOST);

        $apiConfigKey = 'apiCredentials.' . str_replace('.', '-', $apiHost) . '.default';

        $token = $this->config->get($apiConfigKey);

        if ($token) {
            if ($this->isValidToken($token)) {
                return $token;
            }

            Console::warn('Your saved Forge token is invalid!');
            Console::newLine();
        }

        Console::info('Looks like we need a Forge API token, you can get one here:');
        Console::comment('https://forge.laravel.com/user-profile/api');

        do {
            if (isset($isValid)) {
                Console::warn('Invalid token, please try again.');
            }

            $token = Console::secret('Forge API Token');

            $isValid = $this->isValidToken($token);
        } while (!$isValid);

        $this->config->set($apiConfigKey, $token);

        return $token;
    }

    protected function isValidToken($token): bool
    {
        try {
            Http::baseUrl(Client::API_URL)
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
