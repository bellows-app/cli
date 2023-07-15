<?php

namespace Bellows\ProcessManagers\Helpers;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\DisabledByDefault;
use Bellows\Data\EnabledByDefault;
use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Facades\Composer;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Npm;
use Bellows\PluginSdk\Plugin;

class EnabledForDeployment
{
    protected array $cachedDecisions = [];

    public function enabled(Plugin $plugin): bool
    {
        if ($this->hasRequiredPackages($plugin) && $this->getDecision($plugin)->enabled === false) {
            // We've already determined that we don't have the required packages, just skip it.
            return false;
        }

        return Console::confirm(
            'Enable ' . $plugin->getName() . '?',
            $this->getDecision($plugin)->enabled ?? false
        );
    }

    public function isEnabledByDefault(Deployable $plugin): ?DefaultEnabledDecision
    {
        if (isset($this->cachedDecisions[$plugin->getName()])) {
            return $this->cachedDecisions[$plugin->getName()];
        }

        if (count($plugin->requiredComposerPackages())) {
            return $this->decideBasedOnRequiredPackages(
                Composer::class,
                $plugin->requiredComposerPackages(),
            );
        }

        if (count($plugin->requiredNpmPackages())) {
            return $this->decideBasedOnRequiredPackages(
                Npm::class,
                $plugin->requiredNpmPackages(),
            );
        }

        if (count($plugin->anyRequiredComposerPackages())) {
            return $this->decideBasedOnRequiredPackages(
                Composer::class,
                $plugin->anyRequiredComposerPackages(),
                'any',
            );
        }

        if (count($plugin->anyRequiredNpmPackages())) {
            return $this->decideBasedOnRequiredPackages(
                Npm::class,
                $plugin->anyRequiredNpmPackages(),
                'any',
            );
        }

        return null;
    }

    public function hasADefaultDecision(Plugin $plugin)
    {
        $this->getDecision($plugin);

        return isset($this->cachedDecisions[$plugin->getName()]);
    }

    public function getDecision(Plugin $plugin): ?DefaultEnabledDecision
    {
        $this->cachedDecisions[$plugin->getName()] ??= $this->isEnabledByDefault($plugin);

        return $this->cachedDecisions[$plugin->getName()];
    }

    public function getDefaultForConfirmation(Plugin $p): bool
    {
        // TODO: This is a bad name?
        return $p->defaultForDeployConfirmation();
    }

    protected function hasRequiredPackages($plugin): bool
    {
        return collect(
            count($plugin->requiredComposerPackages()),
            count($plugin->requiredNpmPackages()),
            count($plugin->anyRequiredComposerPackages()),
            count($plugin->anyRequiredNpmPackages()),
        )->max() > 0;
    }

    protected function decideBasedOnRequiredPackages(
        string $packageManager,
        array $packages,
        $mode = 'all'
    ): DefaultEnabledDecision {
        $packagesInstalled = $mode === 'all'
            ? $packageManager::allPackagesAreInstalled($packages)
            : $packageManager::anyPackagesAreInstalled($packages);

        $packageList = collect($packages)->implode(', ');
        $descriptor = count($packages) > 1 ? 'are' : 'is';

        $packageManagerName = strtolower($packageManager::getName());

        return $this->getDefaultEnabledDecision(
            $packagesInstalled,
            "{$packageList} {$descriptor} installed [{$packageManagerName}]",
            "{$packageList} {$descriptor} not installed [{$packageManagerName}]",
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

    protected function getDefaultEnabledDecision(
        bool $enabled,
        $messageIfEnabled,
        $messageIfDisabled = null
    ): DefaultEnabledDecision {
        return $enabled
            ? $this->enabledByDefault($messageIfEnabled)
            : $this->disabledByDefault($messageIfDisabled ?? 'Plugin is disabled.');
    }
}
