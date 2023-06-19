<?php

namespace Bellows\Contracts;

use Bellows\PluginSdk\Contracts\Env;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\PluginSdk\Data\InstallRepoParams;
use Bellows\PluginSdk\Data\PhpVersion;
use Bellows\PluginSdk\Data\SecurityRule;
use Bellows\PluginSdk\Data\Server;
use Bellows\PluginSdk\Data\Worker;
use Illuminate\Support\Collection;

interface ServerProviderSite extends SiteInterface
{
    public function installRepo(InstallRepoParams $params): void;

    public function getPhpVersion(): PhpVersion;

    public function env(): Env;

    public function updateEnv(string $env): void;

    public function getDeploymentScript(): string;

    public function isInDeploymentScript(string|iterable $script): bool;

    public function updateDeploymentScript(string $script): void;

    public function getWorkers(): Collection;

    public function createWorker(Worker $worker): array;

    public function createSslCertificate(array $domains): void;

    public function enableQuickDeploy(): void;

    public function addSecurityRule(SecurityRule $rule): array;

    public function delete();

    // TODO: Get rid of this method and just use the get server provider method?
    public function getServer(): Server;

    public function getServerProvider(): ServerProviderServer;
}
