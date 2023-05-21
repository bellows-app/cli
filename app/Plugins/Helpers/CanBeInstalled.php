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
}
