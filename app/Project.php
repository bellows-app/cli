<?php

namespace Bellows;

use Bellows\Data\ProjectConfig;
use Bellows\PluginSdk\Data\PhpVersion;
use Bellows\Util\FileHelper;

class Project
{
    protected Env $env;

    protected ProjectConfig $config;

    protected string $appName;

    protected string $isolatedUser;

    protected string $domain;

    protected string $dir;

    protected PhpVersion $phpVersion;

    public function appName()
    {
        return $this->appName;
    }

    public function setAppName(string $appName): void
    {
        $this->appName = $appName;
    }

    public function isolatedUser(): string
    {
        return $this->isolatedUser;
    }

    public function setIsolatedUser(string $isolatedUser): void
    {
        $this->isolatedUser = $isolatedUser;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function dir(): string
    {
        return $this->dir;
    }

    public function setDir(string $dir): void
    {
        $this->dir = $dir;
    }

    public function phpVersion(): PhpVersion
    {
        return $this->phpVersion;
    }

    public function setPhpVersion(PhpVersion $phpVersion): void
    {
        $this->phpVersion = $phpVersion;
    }

    // TODO: We'll be done with this at some point, remove
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

    public function path(string $path): string
    {
        // In case we are passing in the full of the project already,
        // we need to strip the project dir from the path.
        $path = str_replace($this->dir, '', $path);

        return $this->dir . '/' . ltrim($path, '/');
    }

    public function file(string $path): FileHelper
    {
        return new FileHelper($this->path($path));
    }
}
