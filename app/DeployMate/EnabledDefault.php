<?php

namespace App\DeployMate;

class EnabledDefault extends DefaultEnabledDecision
{
    public function __construct(
        public bool $enabled,
        public string $reason,
    ) {
    }
}
