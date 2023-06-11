<?php

namespace Bellows\Config;

class BellowsConfig
{
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new BellowsConfig();
        }

        return self::$instance;
    }

    public function path(string $path)
    {
        return env('HOME') . '/.bellows/' . ltrim($path, '/');
    }

    public function localPluginPath(string $path)
    {
        return env('HOME') . '/.bellows/local-plugins/' . ltrim($path, '/');
    }

    private function __clone()
    {
    }
}
