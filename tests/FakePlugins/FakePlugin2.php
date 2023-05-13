<?php

namespace Tests\FakePlugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Data\InstallRepoParams;
use Bellows\Plugin;
use Illuminate\Support\Facades\Http;

class FakePlugin2 extends Plugin
{
    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('Yes');
    }

    public function setup(): void
    {
        //
    }

    public function installRepoParams(InstallRepoParams $baseParams): array
    {
        return ['branch' => 'devvo'];
    }

    public function environmentVariables(): array
    {
        return [
            'TEST_ENV_VAR_2' => 'test2',
        ];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return $deployScript;
    }

    public function workers(): array
    {
        return [
            [
                'workers2' => 'second worker',
            ],
        ];
    }

    public function jobs(): array
    {
        return [
            [
                'job2' => 'second job',
            ],
        ];
    }

    public function daemons(): array
    {
        return [];
    }

    public function wrapUp(): void
    {
        Http::get('https://example.com/test2');
    }
}
