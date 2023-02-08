<?php

namespace App\Plugins;

use App\Bellows\Plugin;

class Mailcoach extends Plugin
{
    protected array $requiredComposerPackages = [
        'spatie/laravel-mailcoach-mailer',
    ];
}
