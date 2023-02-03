<?php

namespace App\DeployMate;

use Spatie\LaravelData\Data;

class NewTokenPrompt extends Data
{
    public function __construct(
        public string $url,
        public ?string $helpText = null,
    ) {
    }
}
