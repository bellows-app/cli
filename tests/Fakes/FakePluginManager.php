<?php

namespace Tests\Fakes;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\InstallRepoParams;
use Bellows\PluginManagerInterface;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

class FakePluginManager implements PluginManagerInterface
{
    use RecordsMethodCalls;

    public function __construct()
    {
        $this->recorded = collect();
    }

    public function setPrimarySite(?SiteInterface $site): void
    {
        $this->record(__FUNCTION__);
    }

    public function setPrimaryServer(ServerInterface $primaryServer): void
    {
        $this->record(__FUNCTION__);
    }

    public function setServer(ServerInterface $server): void
    {
        $this->record(__FUNCTION__);
    }

    public function setActiveForLaunch()
    {
        $this->record(__FUNCTION__);
    }

    public function getAllAvailablePluginNames(): Collection
    {
        $this->record(__FUNCTION__);

        return collect();
    }

    public function createSiteParams(CreateSiteParams $params): array
    {
        $this->record(__FUNCTION__, $params);

        return [];
    }

    public function installRepoParams(InstallRepoParams $baseParams): array
    {
        $this->record(__FUNCTION__, $baseParams);

        return [];
    }

    public function environmentVariables(): array
    {
        $this->record(__FUNCTION__);

        return [];
    }

    public function updateDeployScript(string $deployScript): string
    {
        $this->record(__FUNCTION__, $deployScript);

        return $deployScript;
    }

    public function daemons(): Collection
    {
        $this->record(__FUNCTION__);

        return collect();
    }

    public function workers(): Collection
    {
        $this->record(__FUNCTION__);

        return collect();
    }

    public function jobs(): Collection
    {
        $this->record(__FUNCTION__);

        return collect();
    }

    public function wrapUp()
    {
        $this->record(__FUNCTION__);
    }

    public function setSite(SiteInterface $site): void
    {
        $this->record(__FUNCTION__, $site);
    }
}
