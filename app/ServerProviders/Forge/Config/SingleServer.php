<?php

namespace Bellows\ServerProviders\Forge\Config;

use Bellows\Data\PhpVersion;
use Bellows\Facades\Console;
use Bellows\ServerProviders\AsksForDomain;
use Bellows\ServerProviders\ServerDeployTarget;
use Bellows\ServerProviders\Forge\Site;
use Bellows\ServerProviders\ServerInterface;
use Bellows\ServerProviders\SiteInterface;
use Illuminate\Support\Collection;

class SingleServer implements ServerDeployTarget
{
    use AsksForDomain;

    protected string $domain;

    public function __construct(
        protected ServerInterface $server,
    ) {
    }

    public function setup(): void
    {
    }

    public function servers(): Collection
    {
        return collect([$this->server]);
    }

    public function getDomain(): string
    {
        if (isset($this->domain)) {
            return $this->domain;
        }

        $this->domain = $this->askForDomain();

        return $this->domain;
    }

    public function getExistingSite(): ?SiteInterface
    {
        $site = $this->server->getSiteByDomain($this->getDomain());

        if ($site) {
            return new Site($site, $this->server->serverData());
        }

        return null;
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

    public function getPrimarySite(): ?SiteInterface
    {
        return null;
    }
}
