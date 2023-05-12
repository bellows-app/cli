<?php

namespace Tests\Fakes;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\Daemon;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

class FakeServer implements \Bellows\ServerProviders\ServerInterface
{
    use RecordsMethodCalls;

    public function __construct(
        protected ForgeServer $server,
    ) {
        $this->recorded = collect();
    }

    public function validPhpVersionsFromProject(): Collection
    {
        $this->record(__FUNCTION__);

        return collect();
    }

    public function installPhpVersion(string $version): ?PhpVersion
    {
        $this->record(__FUNCTION__, $version);

        return null;
    }

    public function determinePhpVersionFromProject(): PhpVersion
    {
        $this->record(__FUNCTION__);

        return new PhpVersion(
            version: 'php81',
            binary: 'php8.1',
            display: 'PHP 8.1',
        );
    }

    public function serverData(): ForgeServer
    {
        $this->record(__FUNCTION__);

        return $this->server;
    }

    public function phpVersionFromProject(string $projectDir): PhpVersion
    {
        $this->record(__FUNCTION__, $projectDir);

        return new PhpVersion(
            version: 'php81',
            binary: 'php8.1',
            display: 'PHP 8.1',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSites(): Collection
    {
        $this->record(__FUNCTION__);

        return collect();
    }

    public function getSiteByDomain(string $domain): ?ForgeSite
    {
        $this->record(__FUNCTION__, $domain);

        return null;
    }

    public function createSite(CreateSiteParams $params): SiteInterface
    {
        $this->record(__FUNCTION__, $params);

        return app(SiteInterface::class);
    }

    public function createDaemon(Daemon $daemon): array
    {
        $this->record(__FUNCTION__, $daemon);

        return [];
    }

    public function createJob(Job $job): array
    {
        $this->record(__FUNCTION__, $job);

        return [];
    }

    public function getSiteEnv(int $id): string
    {
        $this->record(__FUNCTION__, $id);

        return '';
    }

    public function __get($name)
    {
        return $this->server->{$name};
    }
}
