<?php

namespace Bellows\Util;

use Bellows\Facades\Project;
use Illuminate\Support\Str;

class Vite
{
    public static function addPlugin(string $content)
    {
        $vite = Project::getFile('vite.config.js');

        if (str_contains($vite, $content)) {
            return;
        }

        $content = Str::finish($content, ',');

        $lines = collect(explode(PHP_EOL, $vite));
        $pluginsStart = $lines->search(fn ($l) => str_contains($l, 'plugins: ['));

        $nextLine = $lines->get($pluginsStart + 1);

        // Count the number of spaces at the start of the line
        $spaces = strlen($nextLine) - strlen(ltrim($nextLine));

        $content = collect(explode(PHP_EOL, $content))
            ->map(fn ($l) => str_repeat(' ', $spaces) . $l)
            ->implode(PHP_EOL);

        if ($pluginsStart === false) {
            $lines->prepend($content);
        } else {
            $lines->splice($pluginsStart + 1, 0, $content);
        }

        Project::writeFile('vite.config.js', $lines->implode(PHP_EOL));
    }

    public static function addImport(string $content)
    {
        $vite = Project::getFile('vite.config.js');

        if (Str::contains($vite, $content)) {
            return;
        }

        $content = Str::finish($content, ';');

        $lines = collect(explode(PHP_EOL, $vite));
        $lastImport = $lines->search(fn ($l) => Str::startsWith($l, 'import'));

        if ($lastImport === false) {
            $lines->prepend($content);
        } else {
            $lines->splice($lastImport + 1, 0, $content);
        }

        Project::writeFile('vite.config.js', $lines->implode(PHP_EOL));
    }
}
