<?php

use Bellows\PackageManagers\Composer;

uses(Tests\TestCase::class);

beforeEach(function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);
});

it('can detect if a package is installed', function () {
    $composer = app(Composer::class);

    installComposerPackage('laravel/framework');

    expect($composer->packageIsInstalled('laravel/framework'))->toBeTrue();
});

it('will not freak out if there is no composer json', function () {
    unlink(base_path('tests/stubs/plugins/default/composer.json'));

    $composer = app(Composer::class);

    expect($composer->packageIsInstalled('laravel/framework'))->toBeFalse();
});

it('can detect if a package is not installed', function () {
    $composer = app(Composer::class);

    expect($composer->packageIsInstalled('laravel/framework'))->toBeFalse();
});

it('can detect if all packages are installed', function () {
    $composer = app(Composer::class);

    installComposerPackage('laravel/framework');
    installComposerPackage('something/else');

    expect($composer->allPackagesAreInstalled(['laravel/framework', 'something/else']))->toBeTrue();
});

it('can detect if all packages are not installed', function () {
    $composer = app(Composer::class);

    installComposerPackage('laravel/framework');

    expect($composer->allPackagesAreInstalled(['laravel/framework', 'something/else']))->toBeFalse();
});

it('can detect if any packages are installed', function () {
    $composer = app(Composer::class);

    installComposerPackage('laravel/framework');
    installComposerPackage('something/else');

    expect($composer->anyPackagesAreInstalled(['something/else']))->toBeTrue();
});

it('can detect if any packages are not installed', function () {
    $composer = app(Composer::class);

    installComposerPackage('laravel/framework');

    expect($composer->allPackagesAreInstalled(['something/else']))->toBeFalse();
});
