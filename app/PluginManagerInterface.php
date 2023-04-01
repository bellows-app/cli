<?php

namespace Bellows;

use Bellows\Data\PluginDaemon;
use Bellows\Data\PluginJob;
use Bellows\Data\Worker;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

interface PluginManagerInterface
{
    public function setActive();

    public function getAllAvailablePluginNames(): Collection;

    public function createSiteParams(array $params): array;

    public function installRepoParams(array $baseParams): array;

    public function environmentVariables(): array;

    public function updateDeployScript(string $deployScript): string;

    /**
     * @return Collection<PluginDaemon>
     */
    public function daemons(): Collection;

    /**
     * @return Collection<Worker>
     */
    public function workers(): Collection;

    /**
     * @return Collection<PluginJob>
     */
    public function jobs(): Collection;

    public function wrapUp();

    public function setSite(SiteInterface $site): void;
}
