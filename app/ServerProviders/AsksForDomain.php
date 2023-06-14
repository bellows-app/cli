<?php

namespace Bellows\ServerProviders;

use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Illuminate\Support\Str;

trait AsksForDomain
{
    public function askForDomain()
    {
        $host = parse_url(Project::env()->get('APP_URL'), PHP_URL_HOST);

        return Console::ask('Domain', Str::replace('.test', '.com', $host));
    }
}
