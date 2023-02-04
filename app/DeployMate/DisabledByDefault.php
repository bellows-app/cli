<?php

namespace App\DeployMate;

class DisabledByDefault extends DefaultEnabledDecision
{
    public bool $enabled;

    public function __construct(
        public string $reason,
    ) {
        $this->enabled = false;
    }
}
