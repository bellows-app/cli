<?php

namespace Bellows\ServerProviders;

use Bellows\Data\SecurityRule;
use Bellows\Data\Worker;

interface SiteInterface
{
    public function installRepo(array $params): void;

    public function getEnv(): string;

    public function updateEnv(string $env): void;

    public function getDeploymentScript(): string;

    public function updateDeploymentScript(string $script): void;

    public function createWorker(Worker $worker): array;

    public function createSslCertificate(string $domain): void;

    public function enableQuickDeploy(): void;

    public function addSecurityRule(SecurityRule $rule): array;
}
