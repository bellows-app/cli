<?php

namespace Bellows\Data;

use Bellows\PluginManagers\LaunchManager;
use Spatie\LaravelData\Data;

class LaunchData extends Data
{
    public function __construct(
        public readonly LaunchManager $manager,
        public array $summary = []
    ) {
    }
}
