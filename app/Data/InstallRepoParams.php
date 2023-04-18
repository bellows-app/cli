<?php

namespace Bellows\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapName(SnakeCaseMapper::class)]
class InstallRepoParams extends Data
{
    public function __construct(
        public string $provider,
        public string $repository,
        public string $branch,
        public bool $composer,
    ) {
    }
}
