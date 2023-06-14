<?php

namespace Bellows\Util;

use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ConfigHelper
{
    protected Collection $lines;

    protected Collection $keys;

    protected string $value;

    protected string $path;

    public function update(string $key, mixed $value)
    {
        $this->setup($key, $value);

        File::put(
            $this->path,
            $this->replace(),
        );
    }

    public function append(string $key, mixed $value)
    {
        $this->setup($key, $value);

        File::put(
            $this->path,
            $this->appendValue(),
        );
    }

    protected function appendValue()
    {
        $chunk = $this->findChunk(clone $this->keys, $this->lines);

        $indentedValue = Str::of($this->value)->start(
            Str::repeat(
                ' ',
                ($this->keys->count() + 1) * 4
            ),
        )->finish(',');

        if (Str::endsWith(trim($chunk->last()), '[],')) {
            // It's an empty array, just replace it
            $this->lines->splice(
                $chunk->keys()->first(),
                1,
                Str::replace(
                    '[]',
                    '[' . PHP_EOL
                        . $indentedValue . PHP_EOL
                        . Str::repeat(
                            ' ',
                            $this->keys->count() * 4
                        ) . ']',
                    $chunk->first()
                ),
            );

            return $this->lines->implode(PHP_EOL);
        }

        $lastElement = $chunk->pop();
        $chunk->push($indentedValue);
        $chunk->push($lastElement);

        $this->lines->splice(
            $chunk->keys()->first(),
            $chunk->count() - 1,
            $chunk,
        );

        return $this->lines->implode(PHP_EOL);
    }

    protected function setup(string $key, mixed $value)
    {
        $this->keys = collect(explode('.', $key));

        $path = Project::dir() . '/config/';

        do {
            $filename = $this->keys->shift();

            if (is_dir($path . $filename)) {
                // See if it's in a subdirectory
                $path .= $filename . '/';
                $keepSearching = true;
            } else {
                $path .= $filename . '.php';
                // At this point we either found the file or we didn't, result is the same
                $keepSearching = false;
            }
        } while ($keepSearching && $this->keys->count() > 0);

        $this->path = $path;

        if (File::missing($this->path)) {
            Console::warn('Config file ' . $filename . ' does not exist! Creating now.');

            File::put(
                $this->path,
                collect([
                    '<?php', '', 'return [];',
                ])->implode(PHP_EOL),
            );
        }

        $this->value = Value::quoted(
            $value,
            Value::SINGLE,
            // If it's an array or a function, leave it alone
            fn ($value) => !Str::startsWith($value, '[') && !Str::match('/^[a-zA-Z0-9_]+\(/', $value),
        );

        $this->lines = collect(explode(PHP_EOL, trim(File::get($this->path))));
    }

    protected function replace()
    {
        $chunk = $this->findChunk(clone $this->keys, $this->lines);

        $replacement = $chunk->map(function ($line) {
            // TODO: Account for double quotes
            preg_match(
                '/\s*\'' . $this->keys->last() . '\'\s*=>\s*(.*)/',
                $line,
                $matches
            );

            if (count($matches) === 0) {
                return false;
            }

            return str_replace(
                $matches[1],
                Str::finish($this->value, ','),
                $line,
            );
        })->filter();

        $this->lines->splice(
            $chunk->keys()->first(),
            $chunk->count(),
            $replacement,
        );

        return $this->lines->implode(PHP_EOL);
    }

    protected function findChunk(Collection $keys, Collection $currentChunk): Collection
    {
        $key = $keys->shift();

        $chunkStart = $chunkEnd = $currentChunk->search(
            fn ($l) => Str::startsWith(
                trim($l),
                ["'" . $key . "'", '"' . $key . '"'],
            ),
        );

        if ($chunkEnd === false) {
            // Put the key back on the front of the keys array,
            // it's missing so everything that follows is also missing
            $keys->prepend($key);

            return $this->fillInMissingKeys($keys, $currentChunk);
        }

        $activeArrays = 0;

        $foundEndOfArray = false;

        while ($chunkEnd !== false && $chunkEnd <= $currentChunk->keys()->last() && !$foundEndOfArray) {
            $activeArrays += Str::substrCount($currentChunk->get($chunkEnd), '[');
            $activeArrays -= Str::substrCount($currentChunk->get($chunkEnd), ']');

            if ($activeArrays === 0) {
                $foundEndOfArray = true;
            } else {
                $chunkEnd++;
            }
        }

        if (!$foundEndOfArray) {
            return $currentChunk;
        }

        $newSlice = $currentChunk->slice(
            $chunkStart - $currentChunk->keys()->first(),
            $chunkEnd - $chunkStart + 1,
        );

        if ($keys->isEmpty()) {
            return $newSlice;
        }

        return $this->findChunk($keys, $newSlice);
    }

    protected function fillInMissingKeys(Collection $keys, Collection $currentChunk): Collection
    {
        $phpArr = $this->getMissingKeysAsPhpArray($keys);

        if ($currentChunk->count() === 1) {
            $currentChunk = collect([
                $currentChunk->keys()->first() => $this->indentBasedOnKeys(
                    $keys,
                    trim(
                        $this->createNewChunkFromSingleLine(
                            $currentChunk,
                            // Indent the multi-line value, but trim the whitepace after the "=>"
                            $phpArr
                        )
                    )
                ),
            ]);

            $this->lines->splice(
                $currentChunk->keys()->first(),
                $currentChunk->count(),
                $currentChunk,
            );

            // Re-create the lines from the new string
            $this->lines = collect(explode(PHP_EOL, $this->lines->implode(PHP_EOL)));

            // We now have all of the necessary keys. Play it again, Sam.
            return $this->findChunk(clone $this->keys, $this->lines);
        }

        $isTopLevel = $currentChunk->implode(PHP_EOL) === $this->lines->implode(PHP_EOL);

        if ($isTopLevel && $currentChunk->last() === 'return [];') {
            // Empty config file, just overwrite the last line and off we go
            $currentChunk->pop();

            $currentChunk->push('return [');

            $currentChunk->push(
                $this->indentBasedOnKeys($keys, trim($phpArr, "[]\n"))
            );

            $currentChunk->push('];');

            $this->lines = $currentChunk;

            return $this->findChunk(clone $this->keys, $this->lines);
        }

        $lastElement = $currentChunk->pop();

        // We're in an array (it's a multi-line chunk) so trim off the surrounding brackets
        $currentChunk->push(
            $this->indentBasedOnKeys($keys, trim($phpArr, "[]\n"))
        );

        $currentChunk->push($lastElement);

        $this->lines->splice(
            $currentChunk->keys()->first(),
            $currentChunk->count() - 1,
            $currentChunk,
        );

        if ($isTopLevel) {
            // It adds an extra ]; at the end of the file
            $this->lines->pop();
        }

        $this->lines = collect(explode(PHP_EOL, $this->lines->implode(PHP_EOL)));

        // We now have all of the necessary keys. Play it again, Sam.
        return $this->findChunk(clone $this->keys, $this->lines);
    }

    protected function indentBasedOnKeys(Collection $keys, string $str)
    {
        $lines = collect(explode(PHP_EOL, $str));

        if (Str::endsWith($str, '],')) {
            $firstLineIndent = strlen($lines->first()) - strlen(ltrim($lines->first()));

            $lines = $lines->map(fn ($l) => Str::replaceFirst(
                Str::repeat(' ', $firstLineIndent),
                '',
                $l
            ));
        }

        $usedKeys = $this->keys->count() - $keys->count();

        $indent = Str::repeat(' ', max($usedKeys + $keys->count() - 1, 0) * 4);

        return $lines->map(fn ($l) => $indent . $l)->implode(PHP_EOL);
    }

    protected function createNewChunkFromSingleLine(Collection $currentChunk, string $phpArr): string
    {
        [$currentKey, $currentValue] = explode('=>', $currentChunk->first());

        // Trim off the whitespace on the right for a consistent result
        $currentKey = rtrim($currentKey) . ' => ';

        // Create a new current chunk, but maintain the key
        if (str_contains($currentValue, '[')) {
            return  $currentKey
                // Trim off the brackets to nest the new key within the array
                . '[' . PHP_EOL . Str::finish(trim($phpArr, "[]\n"), ',') . PHP_EOL
                // Trim off the first bracket of the original result,
                // this is the new next value in the array
                . Str::replaceFirst('[', '', ltrim($currentValue));
        }

        return  $currentKey . Str::finish($phpArr, ',');
    }

    protected function getMissingKeysAsPhpArray(Collection $keys): string
    {
        $arr = Arr::undot([$keys->implode('.') => null]);

        $result = Str::of(var_export($arr, true))
            ->replace('array (', '[')
            ->replace(')', ']')
            ->replace('NULL', 'null')
            ->explode(PHP_EOL)
            ->map(fn ($l) => trim($l))
            ->implode(PHP_EOL);

        $result = Str::replace("\n[", ' [', $result);
        $result = collect(explode(PHP_EOL, $result));

        $midpoint = floor($result->count() / 2);

        // Re-indent the array
        return $result->map(
            fn ($l, $i) => Str::repeat(
                ' ',
                ($i < $midpoint ? $i : $result->count() - $i - 1) * 4
            ) . $l
        )->implode(PHP_EOL);
    }
}
