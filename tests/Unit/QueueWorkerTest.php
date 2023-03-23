<?php

use Bellows\DeployScript;
use Bellows\Plugins\QueueWorker;

it('can create a single queue worker', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Connection', 'database')
        ->expectsQuestion('Queue', 'default')
        ->expectsConfirmation('Defaults look ok?', 'yes')
        ->expectsConfirmation('Do you want to add another queue worker?', 'no')
        ->setup();

    $plugin = app(QueueWorker::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->workers())->toHaveCount(1);

    expect($plugin->workers()[0]->toArray())->toBe([
        'connection'   => 'database',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 60,
        'processes'    => 1,
        'stopwaitsecs' => 10,
        'daemon'       => false,
        'force'        => false,
        'tries'        => null,
    ]);

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    expect($deployScript)->toContain('$FORGE_PHP artisan queue:restart');
    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
})->group('plugin');

it('can create multiple queue workers', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Connection', 'database')
        ->expectsQuestion('Queue', 'default')
        ->expectsConfirmation('Defaults look ok?', 'yes')
        ->expectsConfirmation('Do you want to add another queue worker?', 'yes')
        ->expectsQuestion('Connection', 'redis')
        ->expectsQuestion('Queue', 'default')
        ->expectsConfirmation('Defaults look ok?', 'yes')
        ->expectsConfirmation('Do you want to add another queue worker?', 'no')
        ->setup();

    $plugin = app(QueueWorker::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->workers())->toHaveCount(2);

    expect($plugin->workers()[0]->toArray())->toBe([
        'connection'   => 'database',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 60,
        'processes'    => 1,
        'stopwaitsecs' => 10,
        'daemon'       => false,
        'force'        => false,
        'tries'        => null,
    ]);

    expect($plugin->workers()[1]->toArray())->toBe([
        'connection'   => 'redis',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 60,
        'processes'    => 1,
        'stopwaitsecs' => 10,
        'daemon'       => false,
        'force'        => false,
        'tries'        => null,
    ]);

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    expect($deployScript)->toContain('$FORGE_PHP artisan queue:restart');
    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
})->group('plugin');

it('can create a custom queue worker', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Connection', 'database')
        ->expectsQuestion('Queue', 'default')
        ->expectsConfirmation('Defaults look ok?', 'no')
        ->expectsQuestion('Maximum Seconds Per Job', 0)
        ->expectsQuestion('Rest Seconds When Empty', 120)
        ->expectsQuestion('Number of Processes', 1)
        ->expectsQuestion('Graceful Shutdown Seconds', 15)
        ->expectsConfirmation('Run Worker As Daemon', 'yes')
        ->expectsConfirmation('Always Run, Even In Maintenance Mode', 'no')
        ->expectsQuestion('Maximum Tries', null)
        ->expectsConfirmation('Do you want to add another queue worker?', 'no')
        ->setup();

    $plugin = app(QueueWorker::class);

    $plugin->setup();

    $mock->validate();

    expect($plugin->workers())->toHaveCount(1);

    expect($plugin->workers()[0]->toArray())->toBe([
        'connection'   => 'database',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 120,
        'processes'    => 1,
        'stopwaitsecs' => 15,
        'daemon'       => true,
        'force'        => false,
        'tries'        => null,
    ]);
})->group('plugin');
