<?php

use Bellows\Plugins\RunSchedule;

it('can update the deploy script', function () {
    $plugin = app(RunSchedule::class);
    $plugin->setup();

    $jobs = $plugin->jobs();

    expect($jobs)->toHaveCount(1);

    expect($jobs[0]->toArray())->toBe([
        'command'   => 'php81 /home/tester/bellowstester.com/artisan schedule:run',
        'frequency' => 'minutely',
    ]);
})->group('plugin');
