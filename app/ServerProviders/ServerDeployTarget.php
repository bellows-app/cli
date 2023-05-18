<?php

namespace Bellows\ServerProviders;

use Bellows\Data\PhpVersion;
use Illuminate\Support\Collection;

interface ServerDeployTarget
{
    /** @return Collection<ServerInterface> */
    public function servers(): Collection;

    public function getDomain(): string;

    public function determinePhpVersion(): PhpVersion;

    public function getExistingSite(): ?SiteInterface;

    public function getPrimarySite(): ?SiteInterface;

    public function setupForLaunch(): void;

    public function setupForDeploy(SiteInterface $site): void;

    /** @return Collection<SiteInterface> */
    public function getSitesFromPrimary(): Collection;
}
