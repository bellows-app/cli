<?php

namespace Tests\Fakes;

use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;
use Bellows\Env;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;

class FakeSite implements SiteInterface
{
    use RecordsMethodCalls;

    public function __construct(
        protected ForgeSite $site,
        protected ForgeServer $server,
    ) {
        $this->recorded = collect();
    }

    public function isInDeploymentScript(string|iterable $script): bool
    {
        $this->record(__FUNCTION__);

        return false;
    }

    public function getServerProvider(): ServerInterface
    {
        $this->record(__FUNCTION__);

        return new FakeServer($this->server);
    }

    public function getServer(): ForgeServer
    {
        $this->record(__FUNCTION__);

        return $this->server;
    }

    public function installRepo(InstallRepoParams $params): void
    {
        $this->record(__FUNCTION__, $params);
    }

    public function getEnv(): Env
    {
        $this->record(__FUNCTION__);

        return new Env('');
    }

    public function updateEnv(string $env): void
    {
        $this->record(__FUNCTION__, $env);
    }

    public function getDeploymentScript(): string
    {
        $this->record(__FUNCTION__);

        return '';
    }

    public function updateDeploymentScript(string $script): void
    {
        $this->record(__FUNCTION__, $script);
    }

    public function createWorker(Worker $worker): array
    {
        $this->record(__FUNCTION__, $worker);

        return [];
    }

    public function createSslCertificate(array $domains): void
    {
        $this->record(__FUNCTION__, $domains);
    }

    public function enableQuickDeploy(): void
    {
        $this->record(__FUNCTION__);
    }

    public function addSecurityRule(SecurityRule $rule): array
    {
        $this->record(__FUNCTION__, $rule);

        return [];
    }

    public function __get($name)
    {
        return $this->site->{$name};
    }
}
