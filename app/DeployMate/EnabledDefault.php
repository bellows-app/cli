<?php

namespace App\DeployMate;

use Spatie\LaravelData\Data;

class EnabledDefault extends Data
{
    public function __construct(
        public bool $enabled,
        public string $reason,
    ) {
    }
}
