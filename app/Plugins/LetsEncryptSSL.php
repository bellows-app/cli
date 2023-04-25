<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Facades\Project;
use Bellows\Plugin;

// TODO: Things that run on the primary site and server are running twice when they should only run once
// How do indicate this? Implements an interface so we can identify them?
// BUT the APP ENV needs to be correct. Hm.
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
        $this->primarySite->createSslCertificate(Project::config()->domain);
    }

    public function environmentVariables(): array
    {
        return [
            'APP_URL' => 'https://' . Project::config()->domain,
        ];
    }
}
