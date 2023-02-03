<?php

namespace App\Plugins;

use App\DeployMate\Plugin;
use Illuminate\Support\Facades\Http;

class QuickDeploy extends Plugin
{
    public function enabled(): bool
    {
        return $this->confirm('Enable quick deploy?', true);
    }

    public function wrapUp($server, $site): void
    {
        Http::forgeSite()->post('deployment');
    }
}
