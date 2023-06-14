<?php

namespace Bellows\Git;

use Bellows\PluginSdk\Facades\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class Git
{
    public static function user(): ?string
    {
        return trim(Process::run('git config user.name')->output());
    }

    public static function email(): ?string
    {
        return trim(Process::run('git config user.email')->output());
    }

    public static function gitHubUser(): ?string
    {
        return trim(Process::run('git config github.user')->output());
    }

    public static function add(string|array $files = '.')
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        $files = array_map(fn ($file) => escapeshellarg($file), $files);

        Process::runWithOutput('git add ' . implode(' ', $files));
    }

    public static function commit(string $message)
    {
        Process::runWithOutput('git commit -m ' . escapeshellarg($message));
    }

    public static function push()
    {
        Process::runWithOutput('git push');
    }

    public static function ignore(string|iterable $files)
    {
        if (is_string($files)) {
            $files = [$files];
        }

        File::append(
            Project::dir() . '/.gitignore',
            collect($files)->implode(PHP_EOL) . PHP_EOL,
        );
    }
}
