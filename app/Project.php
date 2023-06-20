<?php

namespace Bellows;

use Bellows\PluginSdk\Data\PhpVersion;
use Bellows\PluginSdk\Data\Repository;
use Bellows\Util\FileHelper;

class Project
{
    protected Env $env;

    protected string $appName;

    protected string $isolatedUser;

    protected string $domain;

    protected string $dir;

    protected bool $siteIsSecure;

    protected PhpVersion $phpVersion;

    protected Repository $gitRepository;

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

    public function siteIsSecure(): bool
    {
        return $this->siteIsSecure;
    }

    public function setSiteIsSecure(bool $siteIsSecure): void
    {
        $this->siteIsSecure = $siteIsSecure;
    }

    public function repo(): Repository
    {
        return $this->gitRepository;
    }

    public function setRepo(Repository $repo): void
    {
        $this->gitRepository = $repo;
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
