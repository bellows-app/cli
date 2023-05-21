<?php

namespace Bellows\Plugins;

use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class Helo extends Plugin implements Installable
{
    use CanBeInstalled;

    protected string $connection;

    protected string $database;

    protected string $username;

    protected string $password;

    public function environmentVariables(): array
    {
        return [
            'MAIL_MAILER'                   => 'smtp',
            'MAIL_HOST'                     => '127.0.0.1',
            'MAIL_PORT'                     => '2525',
            'MAIL_USERNAME'                 => Project::config()->appName,
            'MAIL_PASSWORD'                 => null,
            'MAIL_ENCRYPTION'               => null,
            'MAIL_FROM_ADDRESS'             => 'notification@' . Project::config()->domain,
        ];
    }
}
