<?php

namespace Bellows\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class SecurityRule extends Data
{
    public function __construct(
        public string $name,
        public ?string $path,
        #[DataCollectionOf(SecurityRuleCredentials::class)]
        public DataCollection $credentials,
    ) {
    }
}
