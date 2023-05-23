<?php

namespace Bellows\Util;

use Illuminate\Support\Str;

class Value
{
    const DOUBLE = '"';

    const SINGLE = "'";

    public static function quoted($value, $quoteType = null, callable $customChecker = null)
    {
        if (is_bool($value) || in_array($value, ['true', 'false'])) {
            return $value || $value === 'true' ? 'true' : 'false';
        }

        if ($value === null || $value === 'null') {
            return 'null';
        }

        if (Str::startsWith($value, [self::DOUBLE, self::SINGLE])) {
            return $value;
        }

        if ($customChecker && !$customChecker($value)) {
            // The custom checker makes the final call if supplied
            return $value;
        }

        return $quoteType . addslashes($value) . $quoteType;
    }
}
