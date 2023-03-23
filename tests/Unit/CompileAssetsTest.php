<?php

use Bellows\Data\ProjectConfig;
use Bellows\DeployScript;
use Bellows\Plugins\CompileAssets;

afterEach(function () {
    if (file_exists(app(ProjectConfig::class)->projectDirectory . '/yarn.lock')) {
        unlink(app(ProjectConfig::class)->projectDirectory . '/yarn.lock');
    }

    if (file_exists(app(ProjectConfig::class)->projectDirectory . '/package-lock.json')) {
        unlink(app(ProjectConfig::class)->projectDirectory . '/package-lock.json');
    }
});

it('is disabled when there are no lock files', function () {
    $plugin = app(CompileAssets::class);
    expect($plugin->isEnabledByDefault()->enabled)->toBeFalse();
})->group('plugin');

it('is disabled when is no build script', function () {
    touch(app(ProjectConfig::class)->projectDirectory . '/yarn.lock');

    $plugin = app(CompileAssets::class);

    expect($plugin->isEnabledByDefault()->enabled)->toBeFalse();
})->group('plugin');

it('is enabled with a build script and a lock file', function ($lockFile) {
    touch(app(ProjectConfig::class)->projectDirectory . '/' . $lockFile);
    addNpmScript('build');
    $plugin = app(CompileAssets::class);
    expect($plugin->isEnabledByDefault()->enabled)->toBeTrue();
})->group('plugin')->with(['yarn.lock', 'package-lock.json']);

it('adds the correct commands to the deploy script', function ($lockFile, $expected) {
    touch(app(ProjectConfig::class)->projectDirectory . '/' . $lockFile);
    addNpmScript('build');

    $plugin = app(CompileAssets::class);
    $deployScript = $plugin->updateDeployScript(DeployScript::COMPOSER_INSTALL);

    expect($deployScript)->toContain($expected);
    expect($deployScript)->toContain(DeployScript::COMPOSER_INSTALL);
})->group('plugin')->with([
    ['yarn.lock', "yarn\nyarn build"],
    ['package-lock.json', "npm install\nnpm run build"],
]);
