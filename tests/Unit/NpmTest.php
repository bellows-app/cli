<?php

use Bellows\PackageManagers\Npm;

uses(Tests\TestCase::class);

it('can detect if a package is installed', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    $npm = app(Npm::class);

    installNpmPackage('something/random');

    expect($npm->packageIsInstalled('something/random'))->toBeTrue();
});

it('will not freak out if there is no composer json', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    unlink(base_path('tests/stubs/plugins/default/package.json'));

    $npm = app(Npm::class);

    expect($npm->packageIsInstalled('something/random'))->toBeFalse();
});

it('can detect if a package is not installed', function () {
    overrideProjectConfig([
        'projectDirectory' => base_path('tests/stubs/plugins/default'),
    ]);

    $npm = app(Npm::class);

    expect($npm->packageIsInstalled('something/random'))->toBeFalse();
});
