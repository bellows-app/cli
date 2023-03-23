<?php

namespace Bellows\Data;

use Bellows\Enums\JobFrequency;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Data;

class Job extends Data
{
    public function __construct(
        public string $command,
        #[Enum(JobFrequency::class)]
        public JobFrequency $frequency,
    ) {
    }
}
