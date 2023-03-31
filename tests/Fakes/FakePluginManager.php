<?php

namespace Tests\Fakes;

use Bellows\PluginManagerInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

class FakePluginManager implements PluginManagerInterface
{
    public function setActive()
    {
    }

    public function getAllAvailablePluginNames(): Collection
    {
        return collect();
    }

    public function createSiteParams(array $params): array
    {
        return [];
    }

    public function installRepoParams(array $baseParams): array
    {
        return [];
    }

    public function environmentVariables(): array
    {
        return [];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $deployScript;
    }

    public function daemons(): Collection
    {
        return collect();
    }

    public function workers(): Collection
    {
        return collect();
    }

    public function jobs(): Collection
    {
        return collect();
    }

    public function wrapUp()
    {
    }

    public function setSite(SiteInterface $site): void
    {
    }
}
