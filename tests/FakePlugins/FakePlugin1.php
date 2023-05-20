<?php

namespace Tests\FakePlugins;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\DefaultEnabledDecision;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Launchable;
use Illuminate\Support\Facades\Http;

class FakePlugin1 extends Plugin implements Launchable
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('Yes');
    }

    public function launch(): void
    {
        //
    }

    public function createSiteParams(CreateSiteParams $params): array
    {
        return [
            'php_version' => '7.4',
        ];
    }

    public function environmentVariables(): array
    {
        return [
            'TEST_ENV_VAR' => 'test',
        ];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $deployScript;
    }

    public function workers(): array
    {
        return [];
    }

    public function jobs(): array
    {
        return [
            [
                'job1' => 'first job',
            ],
        ];
    }

    public function daemons(): array
    {
        return [
            [
                'daemon1' => 'first daemon',
            ],
        ];
    }

    public function wrapUp(): void
    {
        Http::get('https://example.com/test1');
    }
}
