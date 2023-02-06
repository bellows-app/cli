<?php

namespace App\DeployMate\Data;

use Spatie\LaravelData\Data;

class AddApiCredentialsPrompt extends Data
{
    public function __construct(
        public string $url,
        public array $credentials,
        public ?string $helpText = null,
    ) {
    }
}
