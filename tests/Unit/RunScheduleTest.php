<?php

use Bellows\Data\PluginJob;
use Bellows\Plugins\RunSchedule;

uses(Tests\PluginTestCase::class)->group('plugin');
it('can create the run schedule job', function () {
    $this->plugin()->setup();

    $plugin = app(RunSchedule::class);
    $plugin->setup();

    $jobs = $plugin->jobs();

    expect($jobs)->toHaveCount(1);

    expect($jobs[0])->toBeInstanceOf(PluginJob::class);

    expect($jobs[0]->toArray())->toBe([
        'command'   => 'php81 /home/tester/bellowstester.com/artisan schedule:run',
        'frequency' => 'minutely',
        'user'      => null,
    ]);
});
