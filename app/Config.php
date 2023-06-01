<?php

namespace Bellows;

use Bellows\Config\BellowsConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class Config
{
    protected string $path;

    protected array $config;

    public function __construct()
    {
        $this->path = BellowsConfig::getInstance()->path('config.json');
        $this->createConfigFileIfMissing();
        $this->cacheConfig();
    }

    public function get(string $key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    public function set(string $key, $value): void
    {
        Arr::set($this->config, $key, $value);
        $this->writeAndRefreshCache();
    }

    public function remove(string $key): void
    {
        Arr::forget($this->config, $key);
        $this->writeAndRefreshCache();
    }

    protected function writeAndRefreshCache(): void
    {
        $this->writeConfig();
        $this->cacheConfig();
    }

    protected function writeConfig(): void
    {
        File::put($this->path, json_encode($this->config, JSON_PRETTY_PRINT));
    }

    protected function cacheConfig(): void
    {
        $this->config = File::json($this->path) ?: [];
    }

    protected function createConfigFileIfMissing(): void
    {
        if (File::exists($this->path)) {
            return;
        }

        File::ensureDirectoryExists(dirname($this->path));

        $this->config = [];
        $this->writeConfig();
    }
}
