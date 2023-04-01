<?php

use Bellows\Artisan;

uses(Tests\TestCase::class);

it('can format an artisan command for a deploy script', function () {
    $artisan = app(Artisan::class);
    expect($artisan->inDeployScript('test'))->toBe('$FORGE_PHP artisan test');
});

it('can format an artisan command for a daemon', function () {
    overrideProjectConfig([
        'phpBinary' => 'php81',
    ]);

    $artisan = app(Artisan::class);

    expect($artisan->forDaemon('test'))->toBe('php81 artisan test');
});

it('can format an artisan command for a job', function () {
    overrideProjectConfig([
        'phpBinary'    => 'php81',
        'isolatedUser' => 'tester',
        'domain'       => 'bellowstester.com',
    ]);

    $artisan = app(Artisan::class);

    expect($artisan->forJob('test'))->toBe('php81 /home/tester/bellowstester.com/artisan test');
});
