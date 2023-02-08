<?php

namespace App\Bellows\Data;

class EnabledByDefault extends DefaultEnabledDecision
{
    public bool $enabled;

    public function __construct(
        public string $reason,
    ) {
        $this->enabled = true;
    }
}
