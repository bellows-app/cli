<?php

namespace Bellows\Contracts;

use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Data\CreateSiteParams;
use Bellows\PluginSdk\Data\Daemon;
use Bellows\PluginSdk\Data\Job;
use Bellows\PluginSdk\Data\PhpVersion;
use Bellows\PluginSdk\Data\Server;
use Bellows\PluginSdk\Data\Site;
use Bellows\PluginSdk\Values\RawValue;
use Illuminate\Support\Collection;

interface ServerProviderServer extends ServerInterface
{
    public function determinePhpVersionFromProject(): PhpVersion;

    /** @return Collection<PhpVersion> */
    public function getPhpVersions(): Collection;

    /** @return Collection<PhpVersion> */
    public function validPhpVersionsFromProject(): Collection;

    public function getSiteByDomain(string $domain): ?Site;

    public function createSite(CreateSiteParams $params): ServerProviderSite;

    public function getDaemons(): Collection;

    public function hasDaemon(string|RawValue $command): bool;

    public function createDaemon(Daemon $daemon): array;

    public function getJobs(): Collection;

    public function hasJob(string|RawValue $command): bool;

    public function createJob(Job $job): array;

    public function installPhpVersion(string $version): ?PhpVersion;

    public function serverData(): Server;
}
