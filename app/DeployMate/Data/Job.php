<?php

namespace App\DeployMate\Data;

use App\DeployMate\Enums\JobFrequency;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Enum;

class Job extends Data
{
    public function __construct(
        public string $command,
        #[Enum(JobFrequency::class)]
        public string $frequency,
    ) {
    }
}
