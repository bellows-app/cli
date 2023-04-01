<?php

namespace Bellows\ServerProviders;

use Bellows\Data\Daemon;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Illuminate\Support\Collection;

interface ServerInterface
{
    public function phpVersionFromProject(string $projectDir): PhpVersion;

    /** @return Collection<ForgeSite> */
    public function getSites(): Collection;

    public function getSiteByDomain(string $domain): ?ForgeSite;

    public function createSite(array $params): SiteInterface;

    public function createDaemon(Daemon $daemon): array;

    public function createJob(Job $job): array;

    public function getSiteEnv(int $id): string;
}
