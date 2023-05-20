<?php

use Bellows\DeployScript;
use Bellows\Plugins\Statamic;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can update the deploy script and .env variables', function () {
    $mock = $this->plugin()
        ->expectsConfirmation('Enable git?', 'yes')
        ->expectsQuestion('Git user email (leave blank to use default)', 'joe@joe.codes')
        ->expectsQuestion('Git user name (leave blank to use default)', 'Joe Tannenbaum')
        ->expectsConfirmation('Automatically commit changes?', 'yes')
        ->expectsConfirmation('Automatically push changes?', 'yes')
        ->expectsOutputToContain('To prevent circular deployments')
        ->setup();

    $plugin = app(Statamic::class);
    $plugin->launch();

    $mock->validate();

    $deployScript = $plugin->updateDeployScript(DeployScript::GIT_PULL);

    expect($deployScript)->toContain('if [[ $FORGE_DEPLOY_MESSAGE =~ "[BOT]" ]]; then');
    expect($deployScript)->toContain('echo "AUTO-COMMITTED ON PRODUCTION. NOTHING TO DEPLOY."');
    expect($deployScript)->toContain('exit 0');
    expect($deployScript)->toContain('fi');
    expect($deployScript)->toContain('php please cache:clear');
    expect($deployScript)->toContain(DeployScript::GIT_PULL);

    expect($plugin->environmentVariables())->toBe([
        'STATAMIC_GIT_ENABLED'    => true,
        'STATAMIC_GIT_USER_NAME'  => 'Joe Tannenbaum',
        'STATAMIC_GIT_USER_EMAIL' => 'joe@joe.codes',
        'STATAMIC_GIT_AUTOMATIC'  => true,
        'STATAMIC_GIT_PUSH'       => true,
    ]);
});

it('will skip the environment variables and deploy script check if git is not enabled', function () {
    $mock = $this->plugin()
        ->expectsConfirmation('Enable git?', 'no')
        ->setup();

    $plugin = app(Statamic::class);
    $plugin->launch();

    $mock->validate();

    $deployScript = $plugin->updateDeployScript(DeployScript::GIT_PULL);

    expect($deployScript)->not()->toContain('if [[ $FORGE_DEPLOY_MESSAGE =~ "[BOT]" ]]; then');
    expect($deployScript)->not()->toContain('echo "AUTO-COMMITTED ON PRODUCTION. NOTHING TO DEPLOY."');
    expect($deployScript)->not()->toContain('exit 0');
    expect($deployScript)->not()->toContain('fi');
    expect($deployScript)->toContain('php please cache:clear');
    expect($deployScript)->toContain(DeployScript::GIT_PULL);

    expect($plugin->environmentVariables())->toBe([]);
});

it('will skip the push question if auto commit is off', function () {
    $mock = $this->plugin()
        ->expectsConfirmation('Enable git?', 'yes')
        ->expectsQuestion('Git user email (leave blank to use default)', 'joe@joe.codes')
        ->expectsQuestion('Git user name (leave blank to use default)', 'Joe Tannenbaum')
        ->expectsConfirmation('Automatically commit changes?', 'no')
        ->setup();

    $plugin = app(Statamic::class);
    $plugin->launch();

    $mock->validate();

    $deployScript = $plugin->updateDeployScript(DeployScript::GIT_PULL);

    expect($deployScript)->not()->toContain('if [[ $FORGE_DEPLOY_MESSAGE =~ "[BOT]" ]]; then');
    expect($deployScript)->not()->toContain('echo "AUTO-COMMITTED ON PRODUCTION. NOTHING TO DEPLOY."');
    expect($deployScript)->not()->toContain('exit 0');
    expect($deployScript)->not()->toContain('fi');
    expect($deployScript)->toContain('php please cache:clear');
    expect($deployScript)->toContain(DeployScript::GIT_PULL);

    expect($plugin->environmentVariables())->toBe([
        'STATAMIC_GIT_ENABLED'    => true,
        'STATAMIC_GIT_USER_NAME'  => 'Joe Tannenbaum',
        'STATAMIC_GIT_USER_EMAIL' => 'joe@joe.codes',
    ]);
});
