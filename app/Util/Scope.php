<?php

namespace Bellows\Util;

use Phar;

class Scope
{
    public static function raw(string $class)
    {
        return $class;

        if (!Phar::running()) {
            // Just use the class as passed in
            return $class;
        }

        // We've prefixed the class, but we want the original class name,
        // so remove the prefix and return
        return collect(explode('\\', $class))->splice(1)->implode('\\');
    }
}
