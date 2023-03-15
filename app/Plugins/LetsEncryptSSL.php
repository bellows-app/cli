<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Plugin;
use Illuminate\Support\Facades\Http;

class LetsEncryptSSL extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        if ($this->projectConfig->secureSite) {
            return $this->enabledByDefault('You chose to secure your site');
        }

        return $this->disabledByDefault('You opted out of securing your site');
    }

    public function wrapUp(): void
    {
        Http::forgeSite()->post(
            'certificates/letsencrypt',
            [
                'domains' => [$this->projectConfig->domain],
            ],
        );
    }

    public function setEnvironmentVariables(): array
    {
        return [
            'APP_URL' => "https://{$this->projectConfig->domain}",
        ];
    }
}
