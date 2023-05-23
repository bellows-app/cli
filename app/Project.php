<?php

namespace Bellows;

use Bellows\Data\ProjectConfig;
use Illuminate\Support\Facades\File;

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

    public function fileExists(string $path): bool
    {
        return File::exists($this->dir . '/' . ltrim($path, '/'));
    }

    public function getFile(string $path): string
    {
        return File::get($this->dir . '/' . ltrim($path, '/'));
    }

    public function writeFile(string $path, string $contents): void
    {
        File::put($this->dir . '/' . ltrim($path, '/'), $contents);
    }
}
