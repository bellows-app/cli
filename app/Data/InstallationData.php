<?php

namespace Bellows\Data;

use Bellows\PluginManagers\InstallationManager;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class InstallationData extends Data
{
    public function __construct(
        public readonly InstallationManager $manager,
        public readonly Collection $config,
    ) {
    }
}
