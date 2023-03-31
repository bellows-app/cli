<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Console;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Site implements SiteInterface
{
    protected PendingRequest $client;

    public function __construct(
        protected ForgeSite $site,
        protected ForgeServer $server,
        protected Console $console
    ) {
        $this->client = Http::forge()->baseUrl(
            Forge::API_URL . "/servers/{$server->id}/sites/{$site->id}"
        );
    }

    public function installRepo(array $params): void
    {
        $this->client->post('git', $params);

        do {
            $site = $this->client->get('')->json()['site'];

            sleep(2);
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

    public function createSslCertificate(string $domain): void
    {
        $this->client->post(
            'certificates/letsencrypt',
            [
                'domains' => [$domain],
            ]
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

    public function __get($name)
    {
        return $this->site->{$name};
    }
}
