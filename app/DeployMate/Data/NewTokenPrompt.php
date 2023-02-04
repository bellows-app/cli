<?php

namespace App\DeployMate\Data;

use Spatie\LaravelData\Data;

class NewTokenPrompt extends Data
{
    public function __construct(
        public string $url,
        public ?string $helpText = null,
    ) {
    }
}
