<?php

namespace Bellows\ServerProviders;

use Bellows\Data\ForgeServer;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;
use Bellows\Env;

interface SiteInterface
{
    public function installRepo(InstallRepoParams $params): void;

    public function getEnv(): Env;

    public function updateEnv(string $env): void;

    public function getDeploymentScript(): string;

    public function updateDeploymentScript(string $script): void;

    public function createWorker(Worker $worker): array;

    public function createSslCertificate(array $domain): void;

    public function enableQuickDeploy(): void;

    public function isInDeploymentScript(string|iterable $script): bool;

    public function addSecurityRule(SecurityRule $rule): array;

    public function getServer(): ForgeServer;

    public function getServerProvider(): ServerInterface;
}
