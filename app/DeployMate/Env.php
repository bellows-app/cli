<?php

namespace App\DeployMate;

use Dotenv\Dotenv;

class Env
{
    protected array $parsed;

    public function __construct(
        protected ProjectConfig $config,
    ) {
    }

    protected function parse()
    {
        if ($this->parsed) {
            return;
        }

        $this->parsed = Dotenv::parse(
            file_get_contents(
                $this->config->projectDirectory . '/.env'
            )
        );
    }

    public function get(string $key)
    {
        $this->parse();

        return $this->parsed[$key] ?? null;
    }
}
