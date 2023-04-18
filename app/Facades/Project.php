<?php

namespace Bellows\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bellows\Env env()
 * @method static \Bellows\Data\ProjectConfig config()
 * @method static void setDir(string $dir)
 */
class Project extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'project';
    }
}
