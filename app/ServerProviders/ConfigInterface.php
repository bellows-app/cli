<?php

namespace Bellows\ServerProviders;

use Bellows\Data\PhpVersion;
use Illuminate\Support\Collection;

interface ConfigInterface
{
    /** @return Collection<ServerInterface> */
    public function servers(): Collection;

    public function getDomain(): string;

    public function determinePhpVersion(): PhpVersion;

    public function getExistingSite(): ?SiteInterface;

    public function getPrimarySite(): ?SiteInterface;

    public function setup(): void;
}
