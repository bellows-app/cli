<?php

use Bellows\PackageManagers\Npm;

uses(Tests\TestCase::class);

beforeEach(function () {
    overrideProjectConfig([
        'directory' => base_path('tests/stubs/plugins/default'),
    ]);
});

it('can detect if a package is installed', function () {
    $npm = app(Npm::class);

    installNpmPackage('something/random');

    expect($npm->packageIsInstalled('something/random'))->toBeTrue();
});

it('will not freak out if there is no composer json', function () {
    unlink(base_path('tests/stubs/plugins/default/package.json'));

    $npm = app(Npm::class);

    expect($npm->packageIsInstalled('something/random'))->toBeFalse();
});

it('can detect if a package is not installed', function () {
    $npm = app(Npm::class);

    expect($npm->packageIsInstalled('something/random'))->toBeFalse();
});

it('can detect if all packages are installed', function () {
    $npm = app(Npm::class);

    installNpmPackage('first/thing');
    installNpmPackage('something/else');

    expect($npm->allPackagesAreInstalled(['first/thing', 'something/else']))->toBeTrue();
});

it('can detect if all packages are not installed', function () {
    $npm = app(Npm::class);

    installNpmPackage('first/thing');

    expect($npm->allPackagesAreInstalled(['first/thing', 'something/else']))->toBeFalse();
});

it('can detect if any packages are installed', function () {
    $npm = app(Npm::class);

    installNpmPackage('first/thing');
    installNpmPackage('something/else');

    expect($npm->anyPackagesAreInstalled(['something/else']))->toBeTrue();
});

it('can detect if any packages are not installed', function () {
    $npm = app(Npm::class);

    installNpmPackage('first/thing');

    expect($npm->allPackagesAreInstalled(['something/else']))->toBeFalse();
});
