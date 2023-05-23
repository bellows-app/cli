<?php

namespace Bellows\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bellows\Env env()
 * @method static \Bellows\Data\ProjectConfig config()
 * @method static void setDir(string $dir)
 * @method static void setConfig(\Bellows\Data\ProjectConfig $config)
 * @method static bool fileExists(string $path)
 * @method static string getFile(string $path)
 * @method static void writeFile(string $path, string $contents)
 */
class Project extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'project';
    }
}
