<?php

namespace App\Plugins;

use App\Bellows\Data\DefaultEnabledDecision;
use App\Bellows\Plugin;
use Illuminate\Support\Facades\Http;

class LetsEncryptSSL extends Plugin
{
    // This should run early so that other
    // plugins can know if the site is secure or not.
    public $priority = 100;

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to secure your site');
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

    public function setEnvironmentVariables(array $envVars): array
    {
        return [
            'APP_URL' => "https://{$this->projectConfig->domain}",
        ];
    }
}
