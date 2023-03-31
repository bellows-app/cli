<?php

use Bellows\DeployScript;
use Bellows\Plugins\MomentumTrail;

uses(Tests\PluginTestCase::class)->group('plugin');

it('can update the deploy script', function () {
    $this->plugin()->setup();

    $plugin = app(MomentumTrail::class);
    $plugin->setup();

    $deployScript = $plugin->updateDeployScript(DeployScript::COMPOSER_INSTALL);

    expect($deployScript)->toContain('$FORGE_PHP artisan trail:generate');
    expect($deployScript)->toContain(DeployScript::COMPOSER_INSTALL);
});
