<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class ProjectConfig extends Data
{
    public function __construct(
        public string $isolatedUser,
        public string $repositoryUrl,
        public string $repositoryBranch,
        public PhpVersion $phpVersion,
        public string $directory,
        public string $domain,
        public string $appName,
        public bool $secureSite,
    ) {
    }
}
