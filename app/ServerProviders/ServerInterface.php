<?php

namespace Bellows\ServerProviders;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\Daemon;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Illuminate\Support\Collection;

interface ServerInterface
{
    /** @return Collection<PhpVersion> */
    public function validPhpVersionsFromProject(): Collection;

    /** @return Collection<ForgeSite> */
    public function getSites(): Collection;

    public function getSiteByDomain(string $domain): ?ForgeSite;

    public function createSite(CreateSiteParams $params): SiteInterface;

    public function createDaemon(Daemon $daemon): array;

    public function createJob(Job $job): array;

    public function getSiteEnv(int $id): string;

    public function installPhpVersion(string $version): ?PhpVersion;

    public function determinePhpVersionFromProject(): PhpVersion;

    public function serverData(): ForgeServer;
}
