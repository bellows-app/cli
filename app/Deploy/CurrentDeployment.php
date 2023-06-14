<?php

namespace Bellows\Deploy;

use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\Safety\PreventsCallingFromPlugin;

class CurrentDeployment
{
    use PreventsCallingFromPlugin;

    // The site we're currently deploying to
    protected SiteInterface $site;

    // If load balancing, the primary site, if not, the same as the $site property
    protected SiteInterface $primarySite;

    // The server we're currently deploying to
    protected ServerInterface $server;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerInterface $primaryServer;

    public function confirmDeploy(): bool
    {
        return true;
    }

    public function site(): SiteInterface
    {
        return $this->site;
    }

    public function primarySite(): SiteInterface
    {
        return $this->primarySite;
    }

    public function server(): ServerInterface
    {
        return $this->server;
    }

    public function primaryServer(): ServerInterface
    {
        return $this->primaryServer;
    }

    public function setSite(SiteInterface $site): self
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->site = $site;

        // If we don't have a primary site at this point, also set this as the primary site
        // TODO: Is this an ugly/unexpected side effect? Oof.
        $this->primarySite ??= $site;

        return $this;
    }

    public function setPrimarySite(SiteInterface $site): self
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->primarySite = $site;

        return $this;
    }

    public function setServer(ServerInterface $server): self
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->server = $server;

        return $this;
    }

    public function setPrimaryServer(ServerInterface $primaryServer): self
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->primaryServer = $primaryServer;

        return $this;
    }

    public function confirmChangeValueTo($current, $new, $message)
    {
        return $current === null || $current === $new || Console::confirm("{$message} from {$current}?", true);
    }
}
