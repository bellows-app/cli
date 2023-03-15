<?php

namespace Bellows;

use Bellows\Mixins\Console as MixinsConsole;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class Console
{
    use Macroable;
    use InteractsWithIO {
        choice as protected baseChoice;
    }

    public function choice($question, array $choices, $default = null, $attempts = null, $multiple = false)
    {
        if ($default === null && count($choices) === 1) {
            $default = array_key_first($choices);
        }

        return $this->baseChoice($question, $choices, $default, $attempts, $multiple);
    }

    public function choiceFromCollection(
        $question,
        Collection $collection,
        string $labelKey,
        array|string|callable $default = null,
        $attempts = null,
        $multiple = false
    ) {
        $default = $this->determinDefaultFromCollection($default, $collection, $labelKey);

        $result = $this->choice(
            $question,
            $collection->pluck($labelKey)->toArray(),
            $default,
            $attempts,
            $multiple
        );

        if ($multiple) {
            return $collection->whereIn($labelKey, $result);
        }

        return $collection->firstWhere($labelKey, $result);
    }

    protected function determinDefaultFromCollection($default, Collection $collection, string $labelKey)
    {
        if ($default === null) {
            return null;
        }

        if (is_callable($default)) {
            return $collection->first(fn ($item) => $default($item))[$labelKey] ?? null;
        }

        if (!is_array($default)) {
            $default = [$labelKey, $default];
        }

        [$keyToFind, $value] = $default;

        return $collection->first(
            fn ($item) => $item[$keyToFind] === $value
        )[$keyToFind] ?? null;
    }
}
