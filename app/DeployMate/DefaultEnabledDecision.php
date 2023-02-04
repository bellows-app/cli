<?php

namespace App\DeployMate;

use Spatie\LaravelData\Data;

abstract class DefaultEnabledDecision extends Data
{
    public bool $enabled;
    public string $reason;
}
