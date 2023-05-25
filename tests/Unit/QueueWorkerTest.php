<?php

use Bellows\Data\PluginWorker;
use Bellows\DeployScript;
use Bellows\Plugins\Queue;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can create a single queue worker', function () {
    $mock = $this->plugin()
        ->expectsQuestion('Connection', 'database')
        ->expectsQuestion('Queue', 'default')
        ->expectsConfirmation('Defaults look ok?', 'yes')
        ->expectsConfirmation('Do you want to add another queue worker?', 'no')
        ->setup();

    $plugin = app(Queue::class);

    $plugin->launch();

    $mock->validate();

    $workers = $plugin->workers();

    expect($workers)->toHaveCount(1);

    expect($workers[0])->toBeInstanceOf(PluginWorker::class);

    expect($workers[0]->toArray())->toBe([
        'connection'   => 'database',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 60,
        'processes'    => 1,
        'stopwaitsecs' => 10,
        'daemon'       => false,
        'force'        => false,
        'tries'        => null,
        'php_version'  => null,
    ]);

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    expect($deployScript)->toContain('$FORGE_PHP artisan queue:restart');
    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
});

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

    $plugin = app(Queue::class);

    $plugin->launch();

    $mock->validate();

    $workers = $plugin->workers();

    expect($workers)->toHaveCount(2);

    expect($workers[0])->toBeInstanceOf(PluginWorker::class);

    expect($workers[0]->toArray())->toBe([
        'connection'   => 'database',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 60,
        'processes'    => 1,
        'stopwaitsecs' => 10,
        'daemon'       => false,
        'force'        => false,
        'tries'        => null,
        'php_version'  => null,
    ]);

    expect($workers[1]->toArray())->toBe([
        'connection'   => 'redis',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 60,
        'processes'    => 1,
        'stopwaitsecs' => 10,
        'daemon'       => false,
        'force'        => false,
        'tries'        => null,
        'php_version'  => null,
    ]);

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    expect($deployScript)->toContain('$FORGE_PHP artisan queue:restart');
    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
});

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

    $plugin = app(Queue::class);

    $plugin->launch();

    $mock->validate();

    $workers = $plugin->workers();

    expect($workers)->toHaveCount(1);

    expect($workers[0])->toBeInstanceOf(PluginWorker::class);

    expect($workers[0]->toArray())->toBe([
        'connection'   => 'database',
        'queue'        => 'default',
        'timeout'      => 0,
        'sleep'        => 120,
        'processes'    => 1,
        'stopwaitsecs' => 15,
        'daemon'       => true,
        'force'        => false,
        'tries'        => null,
        'php_version'  => null,
    ]);
});
