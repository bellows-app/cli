<?php

namespace Bellows\Git;

use Bellows\Data\GitRepo;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class Repo
{
    public static function getInfoFromCurrentDirectory(): GitRepo
    {
        $repoUrlResult = trim(
            Process::run('git config --get remote.origin.url')->output()
        );

        $defaultRepo = collect(explode(':', $repoUrlResult))->map(
            fn ($p) => Str::replace('.git', '', $p)
        )->last();

        $branchesResult = Process::run('git branch -a')->output();

        $branches = collect(explode(PHP_EOL, $branchesResult))
            ->map(fn ($b) => trim($b))
            ->filter()
            ->values();

        $mainBranch =  $branches->first(
            fn ($b) => in_array($b, ['main', 'master'])
        );

        $devBranch = $branches->first(
            fn ($b) => in_array($b, ['develop', 'dev', 'development'])
        );

        $branchChoices = $branches->map(
            fn ($b) => Str::of($b)->replace(['*', 'remotes/origin/'], '')->trim()->toString()
        )->filter(
            fn ($b) => !Str::startsWith($b, 'HEAD ->')
        )->filter()->sort()->unique()->values();

        return new GitRepo(
            name: $defaultRepo,
            mainBranch: $mainBranch,
            branches: $branchChoices,
            devBranch: $devBranch,
        );
    }
}
