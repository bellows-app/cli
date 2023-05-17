<?php

namespace Bellows;

use Bellows\Facades\Console;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeployScript
{
    const COMPOSER_INSTALL = '$FORGE_COMPOSER install';

    const GIT_PULL = 'git pull';

    const PHP_RELOAD = '( flock -w 10 9 || exit 1';

    const OCTANE_RELOAD = '! $FORGE_PHP artisan octane:status';

    const MIGRATE = '$FORGE_PHP artisan migrate';

    public static function addAfterComposerInstall(string $currentScript, string|array $toAdd): string
    {
        return static::addAfterLine($currentScript, static::COMPOSER_INSTALL, $toAdd);
    }

    public static function addAfterGitPull(string $currentScript, string|array $toAdd): string
    {
        return static::addAfterLine($currentScript, static::GIT_PULL, $toAdd);
    }

    public static function addBeforePHPReload(string $currentScript, string|array $toAdd): string
    {
        return static::addBeforeLine(
            $currentScript,
            [static::PHP_RELOAD, static::OCTANE_RELOAD],
            $toAdd
        );
    }

    public static function addAfterLine(string $currentScript, string $toFind, string|array $toAdd): string
    {
        [$parts, $index] = static::findLine($currentScript, $toFind);

        if ($index === false) {
            return $currentScript;
        }

        $toAdd = static::formatLines($toAdd, $parts, $index);

        $parts->splice($index + 1, 0, $toAdd);

        return static::cleanUp($parts->implode(PHP_EOL));
    }

    public static function addBeforeLine(string $currentScript, string|array $toFind, string|array $toAdd): string
    {
        [$parts, $index] = static::findLine($currentScript, $toFind);

        if ($index === false) {
            return $currentScript;
        }

        $toAdd = static::formatLines($toAdd, $parts, $index);

        $parts->splice($index, 0, $toAdd);

        return static::cleanUp($parts->implode(PHP_EOL));
    }

    protected static function formatLines(string|array $toAdd, Collection $parts, int $index)
    {
        $toAdd = collect($toAdd)->map('trim');

        // Look for ifs and fis in the parts and if the index falls between them add a tab
        $ifs = $parts->map(fn ($part, $i) => Str::startsWith(trim($part), 'if') ? $i : false)->filter()->values();
        $fis = $parts->map(fn ($part, $i) => Str::startsWith(trim($part), 'fi') ? $i : false)->filter()->values();

        $addTab = $ifs->zip($fis)->first(fn ($pair) => $pair[0] < $index && $pair[1] > $index) !== null;

        if ($addTab) {
            return $toAdd->map(fn ($item) => "\t" . $item)->implode(PHP_EOL);
        }

        $containsArtisanCommand = $toAdd->first(fn ($item) => Str::startsWith($item, '$FORGE_PHP artisan'));

        if ($containsArtisanCommand) {
            // Wrap it in an if to be safe
            // (TODO: Probably should check if the nested if is an artisan check above and wrap in case?)
            $toAdd = $toAdd->map(fn ($item) => "\t" . $item);

            $toAdd->prepend('if [ -f artisan ]; then');
            $toAdd->push('fi');
        }

        return Str::wrap($toAdd->implode(PHP_EOL), PHP_EOL);
    }

    protected static function cleanUp(string $currentScript): string
    {
        return preg_replace('/\n{3,}/', PHP_EOL . PHP_EOL, $currentScript);
    }

    protected static function findLine(string $currentScript, string|array $toFind): array
    {
        $parts = collect(explode(PHP_EOL, $currentScript));

        $toFind = collect($toFind)->map('trim');

        $index = $parts->search(
            fn ($part) => Str::startsWith(trim($part), $toFind) !== false
        );

        if ($index === false) {
            Console::warn("Could not find {$toFind->join(' or ')} in deploy script!");

            return [$parts, $index];
        }

        return [$parts, $index];
    }
}
