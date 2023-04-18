<?php

namespace Bellows;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\DisabledByDefault;
use Bellows\Data\EnabledByDefault;
use Bellows\PackageManagers\Composer;
use Bellows\PackageManagers\Npm;

trait MakesEnabledDecisions
{
    protected DefaultEnabledDecision $cachedDecision;

    protected array $requiredComposerPackages = [];

    protected array $requiredNpmPackages = [];

    protected array $anyRequiredComposerPackages = [];

    protected array $anyRequiredNpmPackages = [];

    public function enabled(): bool
    {
        if ($this->hasRequiredPackages() && $this->getDefaultEnabled()->enabled === false) {
            // We've already determined that we don't have the required packages, just skip it.
            return false;
        }

        return $this->console->confirm(
            'Enable ' . $this->getName() . '?',
            $this->getDefaultEnabled()->enabled ?? false
        );
    }

    public function getName()
    {
        return class_basename(static::class);
    }

    public function isEnabledByDefault(): ?DefaultEnabledDecision
    {
        if (isset($this->cachedDecision)) {
            return $this->cachedDecision;
        }

        if (count($this->requiredComposerPackages)) {
            return $this->ensureRequiredPackagesAreInstalled(
                Composer::class,
                $this->requiredComposerPackages,
            );
        }

        if (count($this->requiredNpmPackages)) {
            return $this->ensureRequiredPackagesAreInstalled(
                Npm::class,
                $this->requiredNpmPackages,
            );
        }

        if (count($this->anyRequiredComposerPackages)) {
            return $this->ensureAnyRequiredPackagesAreInstalled(
                Composer::class,
                $this->anyRequiredComposerPackages,
            );
        }

        if (count($this->anyRequiredNpmPackages)) {
            return $this->ensureAnyRequiredPackagesAreInstalled(
                Npm::class,
                $this->anyRequiredNpmPackages,
            );
        }

        return null;
    }

    public function hasADefaultEnabledDecision()
    {
        $this->getDefaultEnabled();

        return isset($this->cachedDecision);
    }

    public function getDefaultEnabled(): ?DefaultEnabledDecision
    {
        if (isset($this->cachedDecision)) {
            return $this->cachedDecision;
        }

        $result = $this->isEnabledByDefault();

        if ($result !== null) {
            $this->cachedDecision = $result;

            return $this->cachedDecision;
        }

        return null;
    }

    protected function hasRequiredPackages(): bool
    {
        return collect(
            count($this->requiredComposerPackages),
            count($this->requiredNpmPackages),
            count($this->anyRequiredComposerPackages),
            count($this->anyRequiredNpmPackages),
        )->max() > 0;
    }

    protected function ensureRequiredPackagesAreInstalled(string $packageManager, array $packages, $mode = 'all'): DefaultEnabledDecision
    {
        $packagesInstalled = $mode === 'all'
            ? $packageManager::allPackagesAreInstalled($packages)
            : $packageManager::anyPackagesAreInstalled($packages);

        $packageList = collect($packages)->implode(', ');
        $descriptor = count($packages) > 1 ? 'are' : 'is';

        return $this->getDefaultEnabledDecision(
            $packagesInstalled,
            "{$packageList} {$descriptor} installed in this project [{$packageManager}]",
            "{$packageList} {$descriptor} not installed in this project [{$packageManager}]",
        );
    }

    protected function ensureAnyRequiredPackagesAreInstalled(string $packageManager, array $packages): DefaultEnabledDecision
    {
        return $this->ensureRequiredPackagesAreInstalled($packageManager, $packages, 'any');
    }

    protected function enabledByDefault(string $reason): EnabledByDefault
    {
        return new EnabledByDefault($reason);
    }

    protected function disabledByDefault(string $reason): DisabledByDefault
    {
        return new DisabledByDefault($reason);
    }

    protected function getDefaultEnabledDecision(bool $enabled, $messageIfEnabled, $messageIfDisabled = null): DefaultEnabledDecision
    {
        return $enabled ? $this->enabledByDefault($messageIfEnabled) : $this->disabledByDefault($messageIfDisabled ?? 'Plugin is disabled.');
    }
}
