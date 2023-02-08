<?php

namespace App\Bellows\Data;

class DisabledByDefault extends DefaultEnabledDecision
{
    public bool $enabled;

    public function __construct(
        public string $reason,
    ) {
        $this->enabled = false;
    }
}
