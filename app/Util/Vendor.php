<?php

namespace Bellows\Util;

use Bellows\Git\Git;

class Vendor
{
    public static function namespace()
    {
        return collect([
            $_SERVER['COMPOSER_DEFAULT_VENDOR'] ?? null,
            Git::gitHubUser(),
            $_SERVER['USERNAME'] ?? null,
            $_SERVER['USER'] ?? null,
            get_current_user(),
        ])->filter()->first();
    }
}
