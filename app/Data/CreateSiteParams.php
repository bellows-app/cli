<?php

namespace Bellows\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapName(SnakeCaseMapper::class)]
class CreateSiteParams extends Data
{
    public function __construct(
        public string $domain,
        public string $projectType,
        public string $directory,
        public bool $isolated,
        public string $username,
        public string $phpVersion,
    ) {
    }
}
