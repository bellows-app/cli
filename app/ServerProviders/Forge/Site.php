<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Sleep;

class Site implements SiteInterface
{
    protected PendingRequest $client;

    public function __construct(
        protected ForgeSite $site,
        protected ForgeServer $server,
    ) {
        $this->setClient();
    }

    public function installRepo(InstallRepoParams $params): void
    {
        $this->client->post('git', $params->toArray());

        do {
            $site = $this->client->get('')->json()['site'];

            Sleep::for(2)->seconds();
        } while ($site['repository_status'] !== 'installed');

        $this->site = ForgeSite::from($site);
    }

    public function getEnv(): string
    {
        return (string) $this->client->get('env');
    }

    public function updateEnv(string $env): void
    {
        $this->client->put('env', ['content' => $env]);
    }

    public function getDeploymentScript(): string
    {
        return (string) $this->client->get('deployment/script');
    }

    public function updateDeploymentScript(string $script): void
    {
        $this->client->put('deployment/script', ['content' => $script]);
    }

    public function createWorker(Worker $worker): array
    {
        return $this->client->post('workers', $worker->toArray())->json();
    }

    public function createSslCertificate(array $domains): void
    {
        $existing = collect($this->client->get('certificates')->json()['certificates'])->filter(
            fn ($cert) => $cert['active']
        );

        foreach ($existing as $certificate) {
            $certDomains = collect(explode(',', $certificate['domain']))->map(fn ($domain) => trim($domain));

            if ($certDomains->intersect($domains)->count() === count($domains)) {
                // This certificate already exists, no need to create a new one
                return;
            }
        }

        $this->client->post(
            'certificates/letsencrypt',
            ['domains' => $domains],
        );
    }

    public function enableQuickDeploy(): void
    {
        $this->client->post('deployment');
    }

    public function addSecurityRule(SecurityRule $rule): array
    {
        return $this->client->post('security-rules', $rule->toArray())->json();
    }

    public function delete()
    {
        return $this->client->delete('');
    }

    public function getServer(): ForgeServer
    {
        return $this->server;
    }

    protected function setClient(): void
    {
        $this->client = Client::getInstance()->http()->baseUrl(
            Client::API_URL . "/servers/{$this->server->id}/sites/{$this->site->id}"
        );
    }

    public function __get($name)
    {
        return $this->site->{$name};
    }

    public function __serialize(): array
    {
        return [
            'site'   => $this->site,
            'server' => $this->server,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->site = $data['site'];
        $this->server = $data['server'];

        $this->setClient();
    }
}
