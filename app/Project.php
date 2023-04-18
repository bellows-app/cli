<?php

namespace Bellows;

use Bellows\Data\ProjectConfig;

class Project
{
    protected Env $env;

    protected ProjectConfig $config;

    protected string $dir;

    public function setDir(string $dir): void
    {
        $this->dir = $dir;
    }

    public function env(): Env
    {
        if (!isset($this->env)) {
            $this->env = Env::fromDir($this->dir);
        }

        return $this->env;
    }
}
