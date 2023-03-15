<?php

namespace Bellows\Plugins;

use Bellows\Plugin;
use Illuminate\Support\Facades\Http;

class QuickDeploy extends Plugin
{
    public function enabled(): bool
    {
        return $this->console->confirm('Enable quick deploy?', true);
    }

    public function wrapUp(): void
    {
        Http::forgeSite()->post('deployment');
    }
}
