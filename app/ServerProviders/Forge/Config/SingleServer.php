<?php

namespace Bellows\ServerProviders\Forge\Config;

use Bellows\Data\PhpVersion;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\ServerProviders\ConfigInterface;
use Bellows\ServerProviders\ServerInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SingleServer implements ConfigInterface
{
    public function __construct(
        protected ServerInterface $server,
    ) {
    }

    public function servers(): Collection
    {
        return collect([$this->server]);
    }

    public function getDomain(): string
    {
        $host = parse_url(Project::env()->get('APP_URL'), PHP_URL_HOST);

        return Console::ask('Domain', Str::replace('.test', '.com', $host));
    }

    public function determinePhpVersion(): PhpVersion
    {
        return Console::withSpinner(
            title: 'Determining PHP version',
            task: fn () => $this->server->determinePhpVersionFromProject(),
            message: fn (?PhpVersion $result) => $result?->display,
            success: fn ($result) => $result !== null,
        );
    }
}
