<?php

namespace Bellows\ServerProviders;

use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\PluginSdk\Data\PhpVersion;
use Illuminate\Support\Collection;

interface ServerDeployTarget
{
    /** @return Collection<ServerInterface> */
    public function servers(): Collection;

    /** @return Collection<SiteInterface> */
    public function sites(): Collection;

    public function getDomain(): string;

    public function determinePhpVersion(): PhpVersion;

    public function getExistingSite(): ?SiteInterface;

    public function getPrimarySite(): ?SiteInterface;

    public function setupForLaunch(): void;

    public function setupForDeploy(SiteInterface $site): void;
}
