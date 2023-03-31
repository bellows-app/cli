<?php

namespace Bellows;

use Bellows\Data\Daemon;
use Bellows\Data\Job;
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
     * @return Collection<Daemon>
     */
    public function daemons(): Collection;

    /**
     * @return Collection<Worker>
     */
    public function workers(): Collection;

    /**
     * @return Collection<Job>
     */
    public function jobs(): Collection;

    public function wrapUp();

    public function setSite(SiteInterface $site): void;
}
