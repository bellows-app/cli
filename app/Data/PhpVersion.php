<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class PhpVersion extends Data
{
    public function __construct(
        /** e.g. php82 */
        public string $version,
        /** e.g. php8.2 */
        public string $binary,
        /** e.g. PHP 8.2 */
        public string $display,
        public ?string $status = null,
        public ?bool $used_as_default = null,
        public ?bool $used_on_cli = null,
    ) {
    }
}
