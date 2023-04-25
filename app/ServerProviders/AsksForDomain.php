<?php

namespace Bellows\ServerProviders;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Illuminate\Support\Str;

trait AsksForDomain
{
    public function askForDomain()
    {
        $host = parse_url(Project::env()->get('APP_URL'), PHP_URL_HOST);

        return Console::ask('Domain', Str::replace('.test', '.com', $host));
    }
}
