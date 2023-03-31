<?php

namespace Tests\Fakes;

use Bellows\Data\Daemon;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

class FakeServer implements \Bellows\ServerProviders\ServerInterface
{
    public function __construct(
        protected ForgeServer $server,
    ) {
    }

    public function phpVersionFromProject($projectDir): PhpVersion
    {
        return new PhpVersion(name: 'php81', binary: 'php8.1');
    }

    /**
     * {@inheritDoc}
     */
    public function getSites(): Collection
    {
        return collect();
    }

    public function getSiteByDomain(string $domain): ?ForgeSite
    {
        return null;
    }

    public function createSite(array $params): SiteInterface
    {
        return app(SiteInterface::class);
    }

    public function createDaemon(Daemon $daemon): array
    {
        return [];
    }

    public function createJob(Job $job): array
    {
        return [];
    }

    public function getSiteEnv(int $id): string
    {
        return '';
    }

    public function __get($name)
    {
        return $this->server->{$name};
    }
}
