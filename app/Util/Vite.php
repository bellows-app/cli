<?php

namespace Bellows\Util;

use Bellows\Facades\Project;
use Illuminate\Support\Str;

class Vite
{
    public static function addPlugin(string $content)
    {
        $path = collect([
            'vite.config.js',
            'vite.config.ts',
        ])->first(fn ($p) => Project::fileExists($p));

        $vite = Project::getFile($path);

        if (str_contains($vite, $content)) {
            return;
        }

        $content = Str::finish($content, ',');

        $lines = collect(explode(PHP_EOL, $vite));
        $pluginsStart = $lines->search(fn ($l) => str_contains($l, 'plugins: ['));

        $nextLine = $lines->get($pluginsStart + 1);
        // Count the number of spaces at the start of the line to indent the new content
        $spaces = strlen($nextLine) - strlen(ltrim($nextLine));

        $content = collect(explode(PHP_EOL, $content))
            ->map(fn ($l) => str_repeat(' ', $spaces) . $l)
            ->implode(PHP_EOL);

        $activeArrays = 1;

        $foundEndOfArray = false;

        // Is this crazy? Yes. Is the regex reliable... not really. So we're in manual mode. But it works.
        while ($pluginsStart !== false && $pluginsStart < $lines->count() - 1 && !$foundEndOfArray) {
            $pluginsStart++;

            $activeArrays += Str::substrCount($lines->get($pluginsStart), '[');
            $activeArrays -= Str::substrCount($lines->get($pluginsStart), ']');

            if ($activeArrays === 0) {
                $foundEndOfArray = true;
            }
        }

        if ($pluginsStart === false) {
            // TODO: This probably isn't right? What should we do here?
            $lines->prepend($content);
        } else {
            $lines->splice($pluginsStart, 0, $content);
        }

        Project::writeFile($path, $lines->implode(PHP_EOL));
    }

    public static function addImport(string $content)
    {
        Project::file('vite.config.js')->addJsImport($content);
    }
}
