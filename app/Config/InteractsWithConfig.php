<?php

namespace Bellows\Config;

use Illuminate\Support\Arr;

trait InteractsWithConfig
{
    protected function getAllConfigsForApi(string $host)
    {
        return $this->config->get($this->getApiConfigKey($host));
    }

    protected function setApiConfigValue(string $host, string $key, $value)
    {
        return $this->config->set($this->getApiConfigKey($host) . '.' . $key, $value);
    }

    protected function getApiConfigValue(string $host, string $key, ?string $default = null)
    {
        return Arr::get($this->getAllConfigsForApi($host), $key, $default);
    }

    protected function getApiConfigKey(string $host)
    {
        return 'apiCredentials.' . str_replace('.', '-', $host);
    }
}
