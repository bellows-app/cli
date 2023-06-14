<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\Launchable;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Domain;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeDeployed;
use Bellows\PluginSdk\PluginResults\DeploymentResult;

class LetsEncryptSSL extends Plugin implements Launchable, Deployable
{
    use CanBeDeployed;

    public function getName(): string
    {
        return "Let's Encrypt SSL";
    }

    public function defaultForDeployConfirmation(): bool
    {
        return Project::siteIsSecure();
    }

    public function deploy(): ?DeploymentResult
    {
        return DeploymentResult::create()->environmentVariable(
            'APP_URL',
            'https://' . Project::domain(),
        )->wrapUp(function () {
            $domains = [Project::domain()];

            if (Domain::isBaseDomain(Project::domain())) {
                $domains[] = 'www.' . Project::domain();
            }

            Deployment::primarySite()->createSslCertificate($domains);
        });
    }

    public function shouldDeploy(): bool
    {
        // Probably not the best check, but works in a pinch
        return !str_contains(Deployment::site()->env()->get('APP_URL'), 'https://');
    }
}
