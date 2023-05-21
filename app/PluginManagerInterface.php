<?php

namespace Bellows;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\PluginDaemon;
use Bellows\Data\PluginJob;
use Bellows\Data\PluginWorker;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

interface PluginManagerInterface
{
    public function setActiveForInstall();

    public function setActiveForLaunch();

    public function composerPackagesToInstall(): array;

    public function composerDevPackagesToInstall(): array;

    public function npmPackagesToInstall(): array;

    public function npmDevPackagesToInstall(): array;

    public function setActiveForDeploy(SiteInterface $site);

    public function getAllAvailablePluginNames(): Collection;

    public function createSiteParams(CreateSiteParams $params): array;

    public function installRepoParams(InstallRepoParams $baseParams): array;

    public function environmentVariables(): array;

    public function updateDeployScript(string $deployScript): string;

    /**
     * @return Collection<PluginDaemon>
     */
    public function daemons(): Collection;

    /**
     * @return Collection<PluginWorker>
     */
    public function workers(): Collection;

    /**
     * @return Collection<PluginJob>
     */
    public function jobs(): Collection;

    public function wrapUp();

    public function installWrapUp();

    public function setPrimarySite(?SiteInterface $site): void;

    public function setSite(SiteInterface $site): void;

    public function setPrimaryServer(ServerInterface $primaryServer): void;

    public function setServer(ServerInterface $server): void;
}
