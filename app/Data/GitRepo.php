<?php

namespace Bellows\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class GitRepo extends Data
{
    public function __construct(
        public string $name,
        public string $mainBranch,
        public Collection $branches,
        public ?string $devBranch = null,
    ) {
    }
}
