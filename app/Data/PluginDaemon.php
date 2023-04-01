<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class PluginDaemon extends Data
{
    public function __construct(
        public string $command,
        public ?string $user = null,
        public ?string $directory = null,
    ) {
    }
}
