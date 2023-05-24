<?php

namespace Bellows\Plugins\Helpers;

trait CanBeInstalled
{
    public function install(): void
    {
        //
    }

    public function installWrapUp(): void
    {
        //
    }

    public function installCommands(): array
    {
        return [];
    }

    public function composerPackagesToInstall(): array
    {
        if (count($this->requiredComposerPackages)) {
            return $this->requiredComposerPackages;
        }

        if (count($this->anyRequiredComposerPackages)) {
            return [$this->anyRequiredComposerPackages[0]];
        }

        return [];
    }

    public function composerDevPackagesToInstall(): array
    {
        return [];
    }

    public function npmPackagesToInstall(): array
    {
        if (count($this->requiredNpmPackages)) {
            return $this->requiredNpmPackages;
        }

        if (count($this->anyRequiredNpmPackages)) {
            return [$this->anyRequiredNpmPackages[0]];
        }

        return [];
    }

    public function npmDevPackagesToInstall(): array
    {
        return [];
    }

    public function publishTags(): array
    {
        return [];
    }

    public function updateConfig(): array
    {
        return [];
    }
}
