<?php

namespace Bellows\Data;

use Bellows\Config\KickoffConfig;
use Bellows\ProcessManagers\InstallationManager;
use Spatie\LaravelData\Data;

class InstallationData extends Data
{
    public function __construct(
        public readonly InstallationManager $manager,
        public readonly KickoffConfig $config,
    ) {
    }
}
