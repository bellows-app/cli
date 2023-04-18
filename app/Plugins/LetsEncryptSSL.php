<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Plugin;
use Bellows\Project;

class LetsEncryptSSL extends Plugin
{
    public function __construct(
        protected Project $project,
    ) {
    }

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        if ($this->project->config->secureSite) {
            return $this->enabledByDefault('You chose to secure your site');
        }

        return $this->disabledByDefault('You opted out of securing your site');
    }

    public function wrapUp(): void
    {
        $this->loadBalancingSite->createSslCertificate($this->project->config->domain);
    }

    public function environmentVariables(): array
    {
        return [
            'APP_URL' => "https://{$this->project->config->domain}",
        ];
    }
}
