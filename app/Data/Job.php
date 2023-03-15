<?php

namespace Bellows\Data;

use Bellows\Enums\JobFrequency;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Enum;

class Job extends Data
{
    public function __construct(
        public string $command,
        #[Enum(JobFrequency::class)]
        public JobFrequency $frequency,
    ) {
    }
}