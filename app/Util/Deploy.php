<?php

namespace Bellows\Util;

use Bellows\Facades\Console;

class Deploy
{
    public static function wantsToChangeValueTo($current, $new, $message)
    {
        return $current
            && $current !== $new
            && !Console::confirm("{$message} from {$current}?", true);
    }
}