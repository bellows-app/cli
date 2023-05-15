<?php

namespace Bellows\Util;

class SharedAccount
{
    private static $instance = null;

    protected array $accounts = [];

    private function __construct()
    {
    }

    public static function getInstance(): SharedAccount
    {
        if (self::$instance == null) {
            self::$instance = new SharedAccount();
        }

        return self::$instance;
    }

    public function get(string $name): ?callable
    {
        if ($this->has($name)) {
            return $this->accounts[$name];
        }

        return null;
    }

    public function set(string $name, callable $value): void
    {
        $this->accounts[$name] = $value;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->accounts);
    }
}
