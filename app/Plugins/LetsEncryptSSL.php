<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Util\Domain;

class LetsEncryptSSL extends Plugin implements Launchable, Deployable
{
    public function getName(): string
    {
        return "Let's Encrypt SSL";
    }

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        if (Project::config()->secureSite) {
            return $this->enabledByDefault('You chose to secure your site');
        }

        return $this->disabledByDefault('You opted out of securing your site');
    }

    public function launch(): void
    {
        // Nothing to do here
    }

    public function deploy(): bool
    {
        // Nothing to do here
        return true;
    }

    public function canDeploy(): bool
    {
        // Probably not the best check, but works in a pinch
        return !str_contains($this->site->getEnv()->get('APP_URL'), 'https://');
    }

    public function wrapUp(): void
    {
        $domains = [Project::config()->domain];

        if (Domain::isBaseDomain(Project::config()->domain)) {
            $domains[] = 'www.' . Project::config()->domain;
        }

        $this->primarySite->createSslCertificate($domains);
    }

    public function environmentVariables(): array
    {
        return [
            'APP_URL' => 'https://' . Project::config()->domain,
        ];
    }
}
