<?php

namespace App\Plugins;

use App\DeployMate\Plugin;
use Illuminate\Support\Facades\Http;

class LetsEncryptSSL extends Plugin
{
    public function defaultEnabled(): array
    {
        return $this->defaultEnabledPayload(
            true,
            'You probably want to secure your site',
        );
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
