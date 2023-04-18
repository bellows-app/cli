<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class ProjectConfig extends Data
{
    public function __construct(
        public string $isolatedUser,
        public Repository $repository,
        public PhpVersion $phpVersion,
        public string $directory,
        public string $domain,
        public string $appName,
        public bool $secureSite,
    ) {
    }
}
