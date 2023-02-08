<?php

namespace App\Bellows;

use Illuminate\Support\Arr;

trait InteractsWithConfig
{
    protected function getApiConfig(string $host)
    {
        return $this->config->get($this->getApiConfigKey($host));
    }

    protected function setApiConfig(string $host, string $key, $value)
    {
        return $this->config->set($this->getApiConfigKey($host) . '.' . $key, $value);
    }

    protected function getApiConfigValue(string $host, string $key, ?string $default = null)
    {
        return Arr::get($this->getApiConfig($host), $key, $default);
    }

    protected function getPluginConfig()
    {
        return $this->config->get($this->getPluginConfigKey());
    }

    protected function getPluginConfigValue(string $key, ?string $default = null)
    {
        return Arr::get($this->getPluginConfig(), $key, $default);
    }

    protected function setPluginConfig(string $key, $value)
    {
        $this->config->set($this->getPluginConfigKey() . '.' . $key, $value);
    }

    protected function getApiConfigKey(string $host)
    {
        return 'apiCredentials.' . str_replace('.', '-', $host);
    }

    protected function getPluginConfigKey()
    {
        return 'plugins.' . get_class($this);
    }
}
