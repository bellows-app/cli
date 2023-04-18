<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Config;
use Bellows\Data\ForgeServer;
use Bellows\Facades\Console;
use Bellows\ServerProviders\ConfigInterface;
use Bellows\ServerProviders\Forge\Config\LoadBalancer;
use Bellows\ServerProviders\Forge\Config\SingleServer;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\ServerProviderInterface;
use Exception;
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

    public function getServer(): ?ServerInterface
    {
        $servers = collect(
            Client::getInstance()->http()->get('servers')->json()['servers']
        )->filter(fn ($s) => !$s['revoked'])->values();

        if ($servers->isEmpty()) {
            return null;
        }

        if ($servers->count() === 1) {
            $server = $servers->first();

            Console::info("Found only one server, auto-selecting: <comment>{$server['name']}</comment>");

            return new Server(ForgeServer::from($server));
        }

        $serverName = Console::choice(
            'Which server would you like to use?',
            $servers->pluck('name')->sort()->values()->toArray()
        );

        $server = ForgeServer::from($servers->first(fn ($s) => $s['name'] === $serverName));

        return new Server($server);
    }

    public function getConfigFromServer(ServerInterface $server): ConfigInterface
    {
        if ($server->type === 'loadbalancer') {
            Console::miniTask('Detected', 'Load Balancer', true);

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
