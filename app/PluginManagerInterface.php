<?php

namespace Bellows;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\PluginDaemon;
use Bellows\Data\PluginJob;
use Bellows\Data\PluginWorker;
use Bellows\Enums\PluginMode;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

interface PluginManagerInterface
{
    public function setActive();

    public function getAllAvailablePluginNames(): Collection;

    public function createSiteParams(CreateSiteParams $params): array;

    public function installRepoParams(InstallRepoParams $baseParams): array;

    public function environmentVariables(): array;

    public function updateDeployScript(string $deployScript): string;

    public function setMode(PluginMode $mode): void;

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

    public function setPrimarySite(?SiteInterface $site): void;

    public function setSite(SiteInterface $site): void;

    public function setPrimaryServer(ServerInterface $primaryServer): void;

    public function setServer(ServerInterface $server): void;
}
