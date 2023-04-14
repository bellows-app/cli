<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class PhpVersion extends Data
{
    public function __construct(
        public string $version,
        public string $binary,
        public string $display,
    ) {
    }
}
