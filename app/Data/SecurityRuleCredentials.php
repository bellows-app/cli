<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class SecurityRuleCredentials extends Data
{
    public function __construct(
        public string $username,
        public string $password,
    ) {
    }
}
