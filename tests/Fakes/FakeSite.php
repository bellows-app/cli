<?php

namespace Tests\Fakes;

use Bellows\Data\ForgeSite;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;

class FakeSite implements \Bellows\ServerProviders\SiteInterface
{
    use RecordsMethodCalls;

    public function __construct(
        protected ForgeSite $site,
    ) {
        $this->recorded = collect();
    }

    public function installRepo(array $params): void
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

    public function createSslCertificate(string $domain): void
    {
        $this->record(__FUNCTION__, $domain);
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
