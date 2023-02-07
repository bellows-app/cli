<?php

namespace App\DeployMate;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeployScript
{
    const COMPOSER_INSTALL = '$FORGE_COMPOSER install';
    const PHP_RELOAD = '( flock -w 10 9 || exit 1';
    const OCTANE_RELOAD = '! $FORGE_PHP artisan octane:status';
    const MIGRATE = '$FORGE_PHP artisan migrate';

    public function __construct(protected Console $console)
    {
    }

    public function addAfterComposerInstall(string $currentScript, string | array $toAdd): string
    {
        return $this->addAfterLine($currentScript, self::COMPOSER_INSTALL, $toAdd);
    }

    public function addBeforePHPReload(string $currentScript, string | array $toAdd): string
    {
        return $this->addBeforeLine(
            $currentScript,
            [self::PHP_RELOAD, self::OCTANE_RELOAD],
            $toAdd
        );
    }

    public function addAfterLine(string $currentScript, string $toFind, string | array $toAdd): string
    {
        [$parts, $index] = $this->fineLine($currentScript, $toFind);

        if ($index === false) {
            return $currentScript;
        }

        $toAdd = $this->formatLines($toAdd, $parts, $index);

        $parts->splice($index + 1, 0, $toAdd);

        return $this->cleanUp($parts->implode(PHP_EOL));
    }

    public function addBeforeLine(string $currentScript, string | array $toFind, string | array $toAdd): string
    {
        [$parts, $index] = $this->fineLine($currentScript, $toFind);

        if ($index === false) {
            return $currentScript;
        }

        $toAdd = $this->formatLines($toAdd, $parts, $index);

        $parts->splice($index, 0, $toAdd);

        return $this->cleanUp($parts->implode(PHP_EOL));
    }

    public function formatLines(string | array $toAdd, Collection $parts, int $index)
    {
        $toAdd = collect($toAdd)->map('trim');

        // Look for ifs and fis in the parts and if the index falls between them add a tab
        $ifs = $parts->map(fn ($part, $i) => Str::startsWith(trim($part), 'if') ? $i : false)->filter()->values();
        $fis = $parts->map(fn ($part, $i) => Str::startsWith(trim($part), 'fi') ? $i : false)->filter()->values();

        $addTab = $ifs->zip($fis)->first(fn ($pair) => $pair[0] < $index && $pair[1] > $index) !== null;

        if ($addTab) {
            return $toAdd->map(fn ($item) => "\t" . $item)->implode(PHP_EOL);
        }

        return Str::wrap($toAdd->implode(PHP_EOL), PHP_EOL);
    }

    public function cleanUp(string $currentScript): string
    {
        return preg_replace('/\n{3,}/', PHP_EOL . PHP_EOL, $currentScript);
    }

    public function fineLine(string $currentScript, string | array $toFind): array
    {
        $parts = collect(explode(PHP_EOL, $currentScript));

        $toFind = collect($toFind)->map('trim');

        $index = $parts->search(
            fn ($part) => Str::startsWith(trim($part), $toFind) !== false
        );

        if ($index === false) {
            $this->console->warn("Could not find {$toFind->join(' or ')} in deploy script!");

            return [$parts, $index];
        }

        return [$parts, $index];
    }
}
