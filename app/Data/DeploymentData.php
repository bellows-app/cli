<?php

namespace Bellows\Data;

use Bellows\Contracts\DeployableManager;
use Spatie\LaravelData\Data;

class DeploymentData extends Data
{
    public function __construct(
        public readonly DeployableManager $manager,
        public array $summary = []
    ) {
    }
}
