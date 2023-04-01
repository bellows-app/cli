<?php

use Bellows\Data\GitRepo;
use Bellows\Git\Repo;
use Illuminate\Support\Facades\Process;

uses(Tests\TestCase::class);

it('can detect git info from the current directory', function () {
    Process::preventingStrayProcesses();

    Process::fake([
        'git config --get remote.origin.url' => Process::result('git@github.com:joetannenbaum/deploy-mate.git'),
        'git branch -a'                      => Process::result(collect([
            'console-facade',
            'fix-db-permissions-plugin',
            'http-fooling',
            '* launch-refactor',
            'main',
            'dev',
            'namecheap',
            'testing',
            'remotes/origin/HEAD -> origin/main',
            'remotes/origin/console-facade',
            'remotes/origin/http-fooling',
            'remotes/origin/launch-refactor',
            'remotes/origin/main',
            'remotes/origin/namecheap',
            'remotes/origin/testing',
        ])->join(PHP_EOL)),
    ]);

    $result = Repo::getInfoFromCurrentDirectory();

    expect($result)->toBeInstanceOf(GitRepo::class);
    expect($result->name)->toBe('joetannenbaum/deploy-mate');
    expect($result->mainBranch)->toBe('main');
    expect($result->devBranch)->toBe('dev');
    expect($result->branches->toArray())->toEqual([
        'console-facade',
        'dev',
        'fix-db-permissions-plugin',
        'http-fooling',
        'launch-refactor',
        'main',
        'namecheap',
        'testing',
    ]);
});
