<?php

namespace App\DeployMate;

use Illuminate\Console\Concerns\InteractsWithIO;

abstract class Plugin
{
    use InteractsWithIO;
    use InteractsWithConfig;

    protected array $cachedDefaultEnabledPayload;

    protected array $requiredComposerPackages = [];

    protected array $requiredNpmPackages = [];

    protected array $anyRequiredComposerPackages = [];

    protected array $anyRequiredNpmPackages = [];

    protected Composer $composer;

    protected Npm $npm;

    protected DeployScript $deployScript;

    protected Artisan $artisan;

    protected Env $env;

    protected bool $defaultEnabledDecisionIsFinal = false;

    public function __construct(protected ProjectConfig $projectConfig, protected Config $config, $output, $input)
    {
        // TODO: Maybe bind this to the container? Feels outdated and a bit gross.
        $this->output = $output;
        $this->input = $input;
        $this->composer = new Composer($projectConfig);
        $this->npm = new Npm($projectConfig);
        $this->deployScript = new DeployScript($projectConfig);
        $this->artisan = new Artisan($projectConfig);
        $this->env = new Env($projectConfig);
    }

    public function enabled(): bool
    {
        if (
            (count($this->requiredComposerPackages) || count($this->requiredNpmPackages))
            && $this->getDefaultEnabled()['enabled'] === false
        ) {
            // We've already determined that we don't have the required packages, just skip it.
            return false;
        }

        return $this->confirm(
            'Enable ' . $this->getName() . '?',
            $this->getDefaultEnabled()['enabled'] ?? false
        );
    }

    public function getName()
    {
        return class_basename(static::class);
    }

    public function defaultEnabled(): array
    {
        // TODO: Refactor all of this
        if (count($this->requiredComposerPackages)) {
            $packagesInstalled = $this->composer->allPackagesAreInstalled($this->requiredComposerPackages);
            $packageList       = collect($this->requiredComposerPackages)->implode(', ');
            $descriptor        = count($this->requiredComposerPackages) > 1 ? 'are' : 'is';

            return $this->defaultEnabledPayload(
                $packagesInstalled,
                "{$packageList} {$descriptor} installed in this project [composer]",
                "{$packageList} {$descriptor} not installed in this project [composer]",
            );
        }

        if (count($this->requiredNpmPackages)) {
            $packagesInstalled = $this->npm->allPackagesAreInstalled($this->requiredNpmPackages);
            $packageList       = collect($this->requiredNpmPackages)->implode(', ');
            $descriptor        = count($this->requiredNpmPackages) > 1 ? 'are' : 'is';

            return $this->defaultEnabledPayload(
                $packagesInstalled,
                "{$packageList} {$descriptor} installed in this project [npm]",
                "{$packageList} {$descriptor} not installed in this project [npm]",
            );
        }

        if (count($this->anyRequiredComposerPackages)) {
            $packagesInstalled = $this->composer->anyPackagesAreInstalled($this->anyRequiredComposerPackages);
            $packageList       = collect($this->anyRequiredComposerPackages)->join(', ', ' or ');
            $descriptor        = count($this->anyRequiredComposerPackages) > 1 ? 'are' : 'is';

            return $this->defaultEnabledPayload(
                $packagesInstalled,
                "{$packageList} {$descriptor} installed in this project [composer]",
                "{$packageList} {$descriptor} not installed in this project [composer]",
            );
        }

        if (count($this->anyRequiredNpmPackages)) {
            $packagesInstalled = $this->npm->anyPackagesAreInstalled($this->anyRequiredNpmPackages);
            $packageList       = collect($this->anyRequiredNpmPackages)->join(', ', ' or ');
            $descriptor        = count($this->anyRequiredNpmPackages) > 1 ? 'are' : 'is';

            return $this->defaultEnabledPayload(
                $packagesInstalled,
                "{$packageList} {$descriptor} installed in this project [npm]",
                "{$packageList} {$descriptor} not installed in this project [npm]",
            );
        }

        return [];
    }

    public function hasDefaultEnabled()
    {
        $this->getDefaultEnabled();

        return isset($this->cachedDefaultEnabledPayload);
    }

    public function getDefaultEnabled()
    {
        if (isset($this->cachedDefaultEnabledPayload)) {
            return $this->cachedDefaultEnabledPayload;
        }

        $result = $this->defaultEnabled();

        if (count($result) > 0) {
            $this->cachedDefaultEnabledPayload = $result;

            return $this->cachedDefaultEnabledPayload;
        }
    }

    protected function defaultEnabledPayload(bool $enabled, $messageIfEnabled, $messageIfDisabled = null)
    {
        return [
            'enabled' => $enabled,
            'reason'  => $enabled ? $messageIfEnabled : $messageIfDisabled,
        ];
    }

    public function setup($server): void
    {
        //
    }

    public function createSiteParams(array $params): array
    {
        return [];
    }

    public function installRepoParams($server, $site, array $baseParams): array
    {
        return [];
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [];
    }

    public function updateDeployScript($server, $site, string $deployScript): string
    {
        return $deployScript;
    }

    public function workers($server, $site): array
    {
        return [];
    }

    public function jobs($server, $site): array
    {
        return [];
    }

    public function daemons($server, $site): array
    {
        return [];
    }

    public function wrapUp($server, $site): void
    {
        //
    }

    protected function getDefaultNewAccountName(string $token): ?string
    {
        return null;
    }
}
