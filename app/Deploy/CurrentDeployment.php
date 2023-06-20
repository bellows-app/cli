<?php

namespace Bellows\Deploy;

use Bellows\Contracts\ServerProviderServer;
use Bellows\Contracts\ServerProviderSite;
use Bellows\PluginSdk\Facades\Console;
use Bellows\Safety\PreventsCallingFromPlugin;

class CurrentDeployment
{
    use PreventsCallingFromPlugin;

    // The site we're currently deploying to
    protected ServerProviderSite $site;

    // If load balancing, the primary site, if not, the same as the $site property
    protected ?ServerProviderSite $primarySite = null;

    // The server we're currently deploying to
    protected ServerProviderServer $server;

    // If load balancing, the primary server, if not, the same as the $server property
    protected ServerProviderServer $primaryServer;

    public function confirmDeploy(): bool
    {
        return true;
    }

    public function site(): ServerProviderSite
    {
        return $this->site;
    }

    public function primarySite(): ServerProviderSite
    {
        return $this->primarySite;
    }

    public function server(): ServerProviderServer
    {
        return $this->server;
    }

    public function primaryServer(): ServerProviderServer
    {
        return $this->primaryServer;
    }

    public function setSite(ServerProviderSite $site): self
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->site = $site;

        // If we don't have a primary site at this point, also set this as the primary site
        // TODO: Is this an ugly/unexpected side effect? Oof.
        $this->primarySite ??= $site;

        return $this;
    }

    public function setPrimarySite(?ServerProviderSite $site = null): self
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->primarySite = $site;

        return $this;
    }

    public function setServer(ServerProviderServer $server): self
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->server = $server;

        return $this;
    }

    public function setPrimaryServer(ServerProviderServer $primaryServer): self
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
