<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class Repository extends Data
{
    public function __construct(
        public string $url,
        public string $branch,
    ) {
    }
}
