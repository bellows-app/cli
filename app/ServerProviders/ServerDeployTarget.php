<?php

namespace Bellows\ServerProviders;

use Bellows\Data\PhpVersion;
use Illuminate\Support\Collection;
use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;

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
