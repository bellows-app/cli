<?php

namespace Bellows\Git;

use Bellows\Facades\Project;
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

        Process::run('git add ' . implode(' ', $files), function ($type, $line) {
            echo $line;
        });
    }

    public static function commit(string $message)
    {
        Process::run('git commit -m ' . escapeshellarg($message), function ($type, $line) {
            echo $line;
        });
    }

    public static function push()
    {
        Process::run('git push', function ($type, $line) {
            echo $line;
        });
    }

    public static function ignore(...$files)
    {
        collect($files)->each(
            fn ($file) => File::append(Project::config()->directory . '/.gitignore', $file)
        );
    }
}
