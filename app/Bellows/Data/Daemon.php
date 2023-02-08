<?php

namespace App\Bellows\Data;

use Spatie\LaravelData\Data;

class Daemon extends Data
{
    public function __construct(
        public string $command,
        public ?string $user = null,
        public ?string $directory = null,
    ) {
    }
}
