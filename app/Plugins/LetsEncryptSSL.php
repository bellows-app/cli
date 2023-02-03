<?php

namespace App\Plugins;

use App\DeployMate\BasePlugin;
use Illuminate\Support\Facades\Http;

class LetsEncryptSSL extends BasePlugin
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
                'domains' => [$this->config->domain],
            ],
        );
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'APP_URL' => "https://{$this->config->domain}",
        ];
    }
}
