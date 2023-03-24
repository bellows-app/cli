<?php

use Bellows\Commands\PluginTester;

test('globals')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed()
    ->ignoring(PluginTester::class);
