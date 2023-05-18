<?php

namespace Bellows;

use Bellows\Data\ProjectConfig;

class Project
{
    protected Env $env;

    protected ProjectConfig $config;

    protected string $dir;

    public function dir(): string
    {
        return $this->dir;
    }

    public function setDir(string $dir): void
    {
        $this->dir = $dir;
    }

    public function setConfig(ProjectConfig $config): void
    {
        $this->config = $config;
    }

    public function config(): ProjectConfig
    {
        return $this->config;
    }

    public function env(): Env
    {
        $this->env ??= Env::fromDir($this->dir);

        return $this->env;
    }
}
