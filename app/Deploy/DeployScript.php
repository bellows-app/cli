<?php

namespace Bellows;

use Bellows\Facades\Console;
use Bellows\Safety\PreventsCallingFromPlugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeployScript
{
    use PreventsCallingFromPlugin;

    const COMPOSER_INSTALL = '$FORGE_COMPOSER install';

    const GIT_PULL = 'git pull';

    const PHP_RELOAD = '( flock -w 10 9 || exit 1';

    const OCTANE_RELOAD = '! $FORGE_PHP artisan octane:status';

    const MIGRATE = '$FORGE_PHP artisan migrate';

    protected string $script;

    public function set(string $script)
    {
        $this->preventCallingFromPlugin(__METHOD__);

        $this->script = $script;
    }

    public function get(): string
    {
        return $this->script;
    }

    public function addAtBeginning(string|array $toAdd): string
    {
        $formatted = $this->formatLines($toAdd, collect(), 0);

        if (Str::contains($this->script, $formatted)) {
            return $this->script;
        }

        $this->script = $formatted . PHP_EOL . PHP_EOL . $this->script;

        return $this->cleanUp();
    }

    public function addAtEnd(string|array $toAdd): string
    {
        $formatted = $this->formatLines($toAdd, collect(), 0);

        if (Str::contains($this->script, $formatted)) {
            return $this->script;
        }

        $this->script .= PHP_EOL . PHP_EOL . $formatted;

        return $this->cleanUp();
    }

    public function addAfterComposerInstall(string|array $toAdd): string
    {
        return $this->addAfterLine(static::COMPOSER_INSTALL, $toAdd);
    }

    public function addAfterGitPull(string|array $toAdd): string
    {
        return $this->addAfterLine(static::GIT_PULL, $toAdd);
    }

    public function addBeforePHPReload(string|array $toAdd): string
    {
        return $this->addBeforeLine(
            [static::PHP_RELOAD, static::OCTANE_RELOAD],
            $toAdd
        );
    }

    public function addAfterLine(string $toFind, string|array $toAdd): string
    {
        [$parts, $index] = $this->findLine($toFind);

        if ($index === false) {
            return $this->script;
        }

        $toAdd = $this->formatLines($toAdd, $parts, $index);

        $parts->splice($index + 1, 0, $toAdd);

        return $this->cleanUp($parts->implode(PHP_EOL));
    }

    public function addBeforeLine(string|array $toFind, string|array $toAdd): string
    {
        [$parts, $index] = $this->findLine($toFind);

        if ($index === false) {
            return $this->script;
        }

        $toAdd = $this->formatLines($toAdd, $parts, $index);

        $parts->splice($index, 0, $toAdd);

        return $this->cleanUp($parts->implode(PHP_EOL));
    }

    protected function findLine(string|array $toFind): array
    {
        $parts = collect(explode(PHP_EOL, $this->script));

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

    protected function formatLines(string|array $toAdd, Collection $parts, int $index)
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

    protected function cleanUp(): string
    {
        $this->script = preg_replace('/\n{3,}/', PHP_EOL . PHP_EOL, $this->script);

        return $this->script;
    }
}
