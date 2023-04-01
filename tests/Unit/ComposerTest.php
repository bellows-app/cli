<?php

use Bellows\PackageManagers\Composer;

uses(Tests\TestCase::class);

it('can detect if a package is installed', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    $composer = app(Composer::class);

    installComposerPackage('laravel/framework');

    expect($composer->packageIsInstalled('laravel/framework'))->toBeTrue();
});

it('will not freak out if there is no composer json', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    unlink(base_path('tests/stubs/plugins/default/composer.json'));

    $composer = app(Composer::class);

    expect($composer->packageIsInstalled('laravel/framework'))->toBeFalse();
});

it('can detect if a package is not installed', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    $composer = app(Composer::class);

    expect($composer->packageIsInstalled('laravel/framework'))->toBeFalse();
});
