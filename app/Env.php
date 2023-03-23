<?php

namespace Bellows;

use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Env
{
    protected array $parsed;

    public function __construct(
        protected string $raw,
    ) {
        $this->parsed = Dotenv::parse($this->raw);
    }

    public function get(string $key): ?string
    {
        return $this->parsed[$key] ?? null;
    }

    public function all(): array
    {
        return $this->parsed;
    }

    public function update($key, $value, $quote = false): string
    {
        $this->parsed[$key] = (string) $value;

        if ($this->shouldQuote($value, $key, $quote)) {
            $value = '"' . $value . '"';
        }

        if (Str::contains($this->raw, "{$key}=")) {
            $this->raw = preg_replace("/{$key}=.*/", "{$key}={$value}", $this->raw);

            return $this->raw;
        }

        // Look for other keys that are part of the same potential grouping
        $match = $this->getGroupingKeys($key)->map(
            fn ($gk) => Str::matchAll("/^{$gk}_[A-Z0-9_]+=.+/m", $this->raw)->last()
        )->filter()->first();

        if (!$match) {
            // Not part of any grouping, just tack it onto the end
            $this->raw .= PHP_EOL . PHP_EOL . "{$key}={$value}";

            return $this->raw;
        }

        $this->raw = Str::replaceFirst($match, "{$match}\n{$key}={$value}", $this->raw);

        return $this->raw;
    }

    public function toString()
    {
        // Replace multiple newlines with just two
        $this->raw = preg_replace('/\n{3,}/', PHP_EOL . PHP_EOL, $this->raw);

        return $this->raw;
    }

    /**
     * Find other keys that this key might be related to in the .env file.
     */
    protected function getGroupingKeys(string $key): Collection
    {
        $parts = collect(explode('_', $key));

        if (!in_array($parts->first(), ['MIX', 'VITE'])) {
            return collect($parts->first());
        }

        $keys = collect([$parts->slice(0, 2)->implode('_')]);

        $parts->shift();

        $keys->push($parts->first());

        return $keys;
    }

    protected function shouldQuote($value, $key, $quote)
    {
        if ($quote) {
            return true;
        }

        if (Str::startsWith($value, '"')) {
            return false;
        }

        return Str::contains($value, ['${', ' ']) || Str::contains($key, ['PASSWORD']);
    }
}
