<?php

namespace Bellows\Util;

use Bellows\Facades\Project;
use Bellows\PluginSdk\Util\RawValue;

class Artisan
{
    public static function inDeployScript(string $command): string
    {
        return '$FORGE_PHP artisan ' . trim($command);
    }

    public static function forDaemon(string|RawValue $command): string
    {
        if (static::isFineAlready($command)) {
            return $command;
        }

        return Project::config()->phpVersion->binary . ' artisan ' . trim($command);
    }

    public static function forJob(string|RawValue $command): string
    {
        if (static::isFineAlready($command)) {
            return $command;
        }

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

    protected static function isFineAlready(string|RawValue $command): bool
    {
        return $command instanceof RawValue || str_contains($command, 'artisan');
    }
}
