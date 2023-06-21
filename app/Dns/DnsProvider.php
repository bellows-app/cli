<?php

namespace Bellows\Dns;

use Bellows\Safety\PreventsCallingFromPlugin;

class DnsProvider
{
    use PreventsCallingFromPlugin;

    protected AbstractDnsProvider $provider;

    public function set(AbstractDnsProvider $provider)
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->provider = $provider;
    }

    public function available(): bool
    {
        return isset($this->provider);
    }

    public function __call($name, $arguments)
    {
        if (!$this->available()) {
            return null;
        }

        return $this->provider->$name(...$arguments);
    }
}
