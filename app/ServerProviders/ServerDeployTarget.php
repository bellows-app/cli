<?php

namespace Bellows\ServerProviders;

use Bellows\Contracts\ServerProviderServer;
use Bellows\Contracts\ServerProviderSite;
use Bellows\PluginSdk\Data\PhpVersion;
use Illuminate\Support\Collection;

interface ServerDeployTarget
{
    /** @return Collection<ServerProviderServer> */
    public function servers(): Collection;

    /** @return Collection<ServerProviderSite> */
    public function sites(): Collection;

    public function getDomain(): string;

    public function determinePhpVersion(): PhpVersion;

    public function getExistingSite(): ?ServerProviderSite;

    public function getPrimarySite(): ?ServerProviderSite;

    public function setupForLaunch(): void;

    public function setupForDeploy(ServerProviderSite $site): void;
}
