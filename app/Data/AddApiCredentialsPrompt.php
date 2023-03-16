<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class AddApiCredentialsPrompt extends Data
{
    public function __construct(
        public string $url,
        public array $credentials,
        public string $displayName,
        public ?string $helpText = null,
    ) {
    }
}
