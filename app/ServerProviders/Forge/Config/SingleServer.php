<?php

namespace Bellows\ServerProviders\Forge\Config;

use Bellows\Contracts\ServerProviderServer;
use Bellows\Contracts\ServerProviderSite;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\PluginSdk\Data\PhpVersion;
use Bellows\PluginSdk\Facades\Console;
use Bellows\ServerProviders\AsksForDomain;
use Bellows\ServerProviders\Forge\Site;
use Bellows\ServerProviders\ServerDeployTarget;
use Illuminate\Support\Collection;

class SingleServer implements ServerDeployTarget
{
    use AsksForDomain;

    protected string $domain;

    protected SiteInterface $primarySite;

    public function __construct(
        protected ServerProviderServer $server,
    ) {
    }

    public function setupForLaunch(): void
    {
    }

    public function setupForDeploy(ServerProviderSite $site): void
    {
        $this->primarySite = $site;
    }

    public function servers(): Collection
    {
        return collect([$this->server]);
    }

    public function getDomain(): string
    {
        $this->domain ??= $this->askForDomain();

        return $this->domain;
    }

    public function getExistingSite(): ?ServerProviderSite
    {
        $site = Console::withSpinner(
            title: 'Checking for existing domain on ' . $this->server->name,
            task: fn () => $this->server->getSiteByDomain($this->getDomain()),
            message: fn ($result) => $result ? 'Domain already exists on server!' : 'No site found, on we go!',
            success: fn ($result) => $result === null,
        );

        if ($site) {
            return new Site($site, $this->server->serverData());
        }

        return null;
    }

    /** @return Collection<ServerProviderServer> */
    public function sites(): Collection
    {
        return collect([$this->primarySite]);
    }

    public function determinePhpVersion(): PhpVersion
    {
        return Console::withSpinner(
            title: 'Determining PHP version',
            task: fn () => $this->server->determinePhpVersionFromProject(),
            message: fn (?PhpVersion $result) => $result?->display,
            success: fn ($result) => $result !== null,
        );
    }

    public function getPrimarySite(): ?ServerProviderSite
    {
        return null;
    }
}
