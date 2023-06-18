<?php

namespace Bellows\Util;

use Bellows\Config\BellowsConfig;
use Illuminate\Support\Facades\File;

class JsonSchema
{
    public static function cache(string $url)
    {
        File::ensureDirectoryExists(BellowsConfig::getInstance()->path('json-schemas'));

        $filename = md5($url);

        $fullPath = BellowsConfig::getInstance()->path("json-schemas/{$filename}.json");

        if (File::exists($fullPath)) {
            return $fullPath;
        }

        $data = file_get_contents($url);

        if (!is_string($data)) {
            return null;
        }

        File::put($fullPath, $data);

        return $fullPath;
    }
}
