<?php

namespace Tests\Fakes;

use Bellows\Data\ForgeSite;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;

class FakeSite implements \Bellows\ServerProviders\SiteInterface
{
    public function __construct(
        protected ForgeSite $site,
    ) {
    }

    public function installRepo(array $params): void
    {
    }

    public function getEnv(): string
    {
        return '';
    }

    public function updateEnv(string $env): void
    {
    }

    public function getDeploymentScript(): string
    {
        return '';
    }

    public function updateDeploymentScript(string $script): void
    {
    }

    public function createWorker(Worker $worker): array
    {
        return [];
    }

    public function createSslCertificate(string $domain): void
    {
    }

    public function enableQuickDeploy(): void
    {
    }

    public function addSecurityRule(SecurityRule $rule): array
    {
        return [];
    }

    public function __get($name)
    {
        return $this->site->{$name};
    }
}
