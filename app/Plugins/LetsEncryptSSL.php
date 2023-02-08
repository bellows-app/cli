<?php

namespace App\Plugins;

use App\Bellows\Data\DefaultEnabledDecision;
use App\Bellows\Plugin;
use Illuminate\Support\Facades\Http;

class LetsEncryptSSL extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('You probably want to secure your site');
    }

    public function wrapUp($server, $site): void
    {
        Http::forgeSite()->post(
            'certificates/letsencrypt',
            [
                'domains' => [$this->projectConfig->domain],
            ],
        );
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'APP_URL' => "https://{$this->projectConfig->domain}",
        ];
    }
}
