<?php

namespace Bellows\Util;

class RawValue
{
    public function __construct(
        protected string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
