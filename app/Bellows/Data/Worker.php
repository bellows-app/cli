<?php

namespace App\Bellows\Data;

use Spatie\LaravelData\Data;

class Worker extends Data
{
    public function __construct(
        public string $connection,
        public string $queue,
        public ?int $timeout = 0,
        public ?int $sleep = 60,
        public ?int $processes = 1,
        public ?int $stopwaitsecs = 10,
        public ?bool $daemon = false,
        public ?bool $force = false,
        public ?int $tries = null,
    ) {
    }
}
