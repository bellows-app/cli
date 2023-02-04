<?php

namespace App\DeployMate\Data;

use Spatie\LaravelData\Data;

class ProjectConfig extends Data
{
    public function __construct(
        public string $isolatedUser,
        public string $repositoryUrl,
        public string $repositoryBranch,
        public string $phpVersion,
        public string $phpBinary,
        public string $projectDirectory,
        public string $domain,
        public string $appName,
    ) {
    }
}
