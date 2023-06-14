<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\Launchable;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeDeployed;
use Bellows\PluginSdk\PluginResults\DeploymentResult;

class QuickDeploy extends Plugin implements Launchable, Deployable
{
    use CanBeDeployed;

    public function deploy(): ?DeploymentResult
    {
        return DeploymentResult::create()->wrapUp(fn () => Deployment::site()->enableQuickDeploy());
    }

    public function shouldDeploy(): bool
    {
        return !Deployment::site()->quick_deploy;
    }
}
