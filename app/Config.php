<?php

namespace Bellows;

use Illuminate\Support\Arr;

class Config
{
    protected string $path;

    protected array $config;

    public function __construct()
    {
        $home = env('HOME');

        $dir = $home . '/.bellows';

        $this->path = $dir . '/config.json';

        if (!file_exists($this->path)) {
            if (is_dir($dir) || mkdir($dir)) {
                $this->createConfigFile($this->path);
            }
        }

        $this->cacheConfig();
    }

    public function get(string $key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    public function set(string $key, $value)
    {
        Arr::set($this->config, $key, $value);

        file_put_contents($this->path, json_encode($this->config, JSON_PRETTY_PRINT));

        // We've changed the config, re-cache it
        $this->cacheConfig();
    }

    public function remove(string $key)
    {
        Arr::forget($this->config, $key);

        file_put_contents($this->path, json_encode($this->config, JSON_PRETTY_PRINT));

        // We've changed the config, re-cache it
        $this->cacheConfig();
    }

    protected function createConfigFile(string $path)
    {
        file_put_contents($path, json_encode([]));
    }

    protected function cacheConfig()
    {
        $this->config = json_decode(file_get_contents($this->path), true) ?: [];
    }
}
