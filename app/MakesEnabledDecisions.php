<?php

namespace Bellows;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\DisabledByDefault;
use Bellows\Data\EnabledByDefault;

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

    protected function hasRequiredPackages(): bool
    {
        return collect(
            count($this->requiredComposerPackages),
            count($this->requiredNpmPackages),
            count($this->anyRequiredComposerPackages),
            count($this->anyRequiredNpmPackages),
        )->max() > 0;
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
            return $this->ensureRequiredPackagesAreInstalled('composer');
        }

        if (count($this->requiredNpmPackages)) {
            return $this->ensureRequiredPackagesAreInstalled('npm');
        }

        if (count($this->anyRequiredComposerPackages)) {
            return $this->ensureAnyRequiredPackagesAreInstalled('composer');
        }

        if (count($this->anyRequiredNpmPackages)) {
            return $this->ensureAnyRequiredPackagesAreInstalled('npm');
        }

        return null;
    }

    protected function ensureRequiredPackagesAreInstalled(string $packageManager): DefaultEnabledDecision
    {
        $packagePropertyName = ucfirst(strtolower($packageManager));
        $property = "required{$packagePropertyName}Packages";

        $packagesInstalled = $this->$packageManager->allPackagesAreInstalled($this->$property);
        $packageList       = collect($this->$property)->implode(', ');
        $descriptor        = count($this->$property) > 1 ? 'are' : 'is';

        return $this->getDefaultEnabledDecision(
            $packagesInstalled,
            "{$packageList} {$descriptor} installed in this project [{$packageManager}]",
            "{$packageList} {$descriptor} not installed in this project [{$packageManager}]",
        );
    }

    protected function ensureAnyRequiredPackagesAreInstalled(string $packageManager): DefaultEnabledDecision
    {
        $packagePropertyName = ucfirst(strtolower($packageManager));
        $property = "anyRequired{$packagePropertyName}Packages";

        $packagesInstalled = $this->$packageManager->anyPackagesAreInstalled($this->$property);
        $packageList       = collect($this->$property)->implode(', ');
        $descriptor        = count($this->$property) > 1 ? 'are' : 'is';

        return $this->getDefaultEnabledDecision(
            $packagesInstalled,
            "{$packageList} {$descriptor} installed in this project [{$packageManager}]",
            "{$packageList} {$descriptor} not installed in this project [{$packageManager}]",
        );
    }

    protected function enabledByDefault(string $reason): EnabledByDefault
    {
        return new EnabledByDefault($reason);
    }

    protected function disabledByDefault(string $reason): DisabledByDefault
    {
        return new DisabledByDefault($reason);
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

    protected function getDefaultEnabledDecision(bool $enabled, $messageIfEnabled, $messageIfDisabled = null): DefaultEnabledDecision
    {
        return $enabled ? $this->enabledByDefault($messageIfEnabled) : $this->disabledByDefault($messageIfDisabled ?? 'Plugin is disabled.');
    }
}
