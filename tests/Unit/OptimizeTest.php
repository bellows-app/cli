<?php

use Bellows\DeployScript;
use Bellows\Plugins\Optimize;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can update the deploy script', function () {
    $this->plugin()->setup();

    $plugin = app(Optimize::class);
    $plugin->launch();

    $deployScript = $plugin->updateDeployScript(DeployScript::PHP_RELOAD);

    collect([
        '$FORGE_PHP artisan config:cache',
        '$FORGE_PHP artisan route:cache',
        '$FORGE_PHP artisan view:cache',
        '$FORGE_PHP artisan event:cache',
    ])->each(fn ($toInsert) => expect($deployScript)->toContain($toInsert));

    expect($deployScript)->toContain(DeployScript::PHP_RELOAD);
});
