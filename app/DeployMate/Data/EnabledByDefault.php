<?php

namespace App\DeployMate\Data;

class EnabledByDefault extends DefaultEnabledDecision
{
    public bool $enabled;

    public function __construct(
        public string $reason,
    ) {
        $this->enabled = true;
    }
}
