<?php

use Bellows\DeployScript;
use Bellows\Plugins\MomentumTrail;

it('can update the deploy script', function () {
    $plugin = app(MomentumTrail::class);
    $plugin->setup();

    $deployScript = $plugin->updateDeployScript(DeployScript::COMPOSER_INSTALL);

    expect($deployScript)->toContain('$FORGE_PHP artisan trail:generate');
    expect($deployScript)->toContain(DeployScript::COMPOSER_INSTALL);
})->group('plugin');
