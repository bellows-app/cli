<?php

namespace Bellows;

use Bellows\Data\ProjectConfig;
use Bellows\Util\FileHelper;
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
        return File::exists($this->path($path));
    }

    public function getFile(string $path): string
    {
        return File::get($this->path($path));
    }

    public function writeFile(string $path, string $contents): void
    {
        File::put($this->path($path), $contents);
    }

    public function path(string $path): string
    {
        return $this->dir . '/' . ltrim($path, '/');
    }

    public function file(string $path): FileHelper
    {
        return new FileHelper($this->path($path));
    }
}
