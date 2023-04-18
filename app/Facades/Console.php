<?php

namespace Bellows\Facades;

use Illuminate\Support\Facades\Facade;

class Console extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'console';
    }
}
