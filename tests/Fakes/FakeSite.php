<?php

namespace Tests\Fakes;

use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;

class FakeSite implements \Bellows\ServerProviders\SiteInterface
{
    use RecordsMethodCalls;

    public function __construct(
        protected ForgeSite $site,
        protected ForgeServer $server,
    ) {
        $this->recorded = collect();
    }

    public function getServer(): ForgeServer
    {
        return $this->server;
    }

    public function installRepo(InstallRepoParams $params): void
    {
        $this->record(__FUNCTION__, $params);
    }

    public function getEnv(): string
    {
        $this->record(__FUNCTION__);

        return '';
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
