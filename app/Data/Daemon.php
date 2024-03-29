<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class Daemon extends Data
{
    public function __construct(
        public string $command,
        public string $user,
        public string $directory,
    ) {
    }
}
