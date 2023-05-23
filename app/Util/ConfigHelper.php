<?php

namespace Bellows\Util;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConfigHelper
{
    protected Collection $lines;

    protected Collection $keys;

    public function replace(string $content, string $key, string $value)
    {
        $this->lines = collect(explode(PHP_EOL, trim($content)));

        $this->keys = collect(explode('.', $key));

        $chunkKeys = collect(explode('.', $key));

        $chunk = $this->findChunk($chunkKeys, $this->lines);

        $replacement = $chunk->map(function ($line) use ($value) {
            // TODO: Account for double quotes
            $regex = '/\s*\'' . $this->keys->last() . '\'\s*=>\s*(.*)/';

            ray($regex);

            preg_match($regex, $line, $matches);

            if (count($matches) === 0) {
                return false;
            }

            return str_replace(
                $matches[1],
                Str::finish($value, ','),
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
                $currentChunk->keys()->first() =>
                $this->indentBasedOnKeys(
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

        $lastElement = $currentChunk->pop();

        // We're in an array (it's a multi-line chunk) so trim off the surrounding brackets
        $currentChunk->push(
            $this->indentBasedOnKeys($keys, trim($phpArr, "[]\n"))
        );

        $currentChunk->push($lastElement);

        $this->lines->splice(
            $currentChunk->keys()->first(),
            $currentChunk->count() - $keys->count(),
            $currentChunk,
        );

        if ($isTopLevel) {
            // It adds an extra ]; at the end of the file
            $this->lines->pop();
        }

        return $this->findChunk(clone $this->keys, $this->lines);
    }

    protected function indentBasedOnKeys(Collection $keys, string $str)
    {
        $usedKeys = $this->keys->count() - $keys->count();

        $indent = Str::repeat(' ', ($usedKeys + $keys->count() - 1) * 4);

        return collect(explode(PHP_EOL, $str))
            ->map(fn ($l) => $indent . $l)
            ->implode(PHP_EOL);
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
            ->toString();

        $result = collect(explode(PHP_EOL, $result))
            ->map(fn ($l) => trim($l), $result);

        $midpoint = floor($result->count() / 2);

        // Re-indent the array
        $result = $result->map(
            fn ($l, $i) => Str::repeat(
                ' ',
                ($i < $midpoint ? $i : $result->count() - $i - 1) * 4
            ) . $l
        )->implode(PHP_EOL);

        return str_replace("\n[", ' [', $result);
    }
}
