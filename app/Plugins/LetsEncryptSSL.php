<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Util\Domain;

class LetsEncryptSSL extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        if (Project::config()->secureSite) {
            return $this->enabledByDefault('You chose to secure your site');
        }

        return $this->disabledByDefault('You opted out of securing your site');
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
