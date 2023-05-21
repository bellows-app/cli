<?php

namespace Bellows;

use Bellows\Facades\Project;

class Artisan
{
    public static function inDeployScript(string $command): string
    {
        return '$FORGE_PHP artisan ' . trim($command);
    }

    public static function forDaemon(string $command): string
    {
        return Project::config()->phpVersion->binary . ' artisan ' . trim($command);
    }

    public static function forJob(string $command): string
    {
        $artisanPath = '/' . collect([
            'home',
            Project::config()->isolatedUser,
            Project::config()->domain,
            'artisan',
        ])->join('/');

        return collect([
            Project::config()->phpVersion->binary,
            $artisanPath,
            trim($command),
        ])->join(' ');
    }

    public static function local(string $command): string
    {
        return 'php artisan ' . trim($command);
    }
}
