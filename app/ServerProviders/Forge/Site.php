<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Contracts\ServerProviderServer;
use Bellows\Contracts\ServerProviderSite;
use Bellows\Data\ForgeSite;
use Bellows\Env as BellowsEnv;
use Bellows\PluginSdk\Contracts\Env;
use Bellows\PluginSdk\Data\InstallRepoParams;
use Bellows\PluginSdk\Data\PhpVersion;
use Bellows\PluginSdk\Data\SecurityRule;
use Bellows\PluginSdk\Data\Server as ServerData;
use Bellows\PluginSdk\Data\Site as SiteData;
// TODO: Should this data come from the plugin sdk?
use Bellows\PluginSdk\Data\Worker;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class Site implements ServerProviderSite
{
    protected PendingRequest $client;

    protected Env $env;

    protected string $deploymentScript;

    protected Collection $workers;

    protected Collection $securityRules;

    public function __construct(
        protected SiteData $site,
        protected ServerData $server,
    ) {
        $this->setClient();
    }

    public function data(): SiteData
    {
        return $this->site;
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

    public function getPhpVersion(): PhpVersion
    {
        return $this->getServerProvider()->getPhpVersions()->first(
            fn (PhpVersion $version) => $version->version === $this->site->php_version
        );
    }

    public function env(): Env
    {
        $this->env ??= new BellowsEnv((string) $this->client->get('env'));

        return $this->env;
    }

    public function updateEnv(string $env): void
    {
        $this->client->put('env', ['content' => $env]);
    }

    public function getDeploymentScript(): string
    {
        $this->deploymentScript ??= (string) $this->client->get('deployment/script');

        return $this->deploymentScript;
    }

    public function isInDeploymentScript(string|iterable $script): bool
    {
        return Str::contains($this->getDeploymentScript(), $script);
    }

    public function updateDeploymentScript(string $script): void
    {
        $this->client->put('deployment/script', ['content' => $script]);
    }

    public function getWorkers(): Collection
    {
        $this->workers ??= collect($this->client->get('workers')->json()['workers'])->map(
            fn ($worker) => Worker::from($worker)
        );

        return $this->workers;
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

    // TODO: Get rid of this method and just use the get server provider method?
    public function getServer(): ServerData
    {
        return $this->server;
    }

    public function getServerProvider(): ServerProviderServer
    {
        return new Server($this->server);
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
