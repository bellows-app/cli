<?php

namespace Bellows\Util;

use Bellows\PluginSdk\Values\RawValue;
use Illuminate\Support\Str;

class Value
{
    const DOUBLE = '"';

    const SINGLE = "'";

    public static function quoted($value, $quoteType = null, callable $customChecker = null)
    {
        if ($value instanceof RawValue) {
            return (string) $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if ($value === null || $value === 'null') {
            return 'null';
        }

        if (is_bool($value) || in_array($value, ['true', 'false'])) {
            return $value === true || $value === 'true' ? 'true' : 'false';
        }

        if (Str::endsWith($value, '::class')) {
            return $value;
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

    public static function raw($value)
    {
        return new RawValue($value);
    }
}
