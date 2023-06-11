<?php

namespace Bellows;

use Bellows\Exceptions\EnvMissing;
use Bellows\PluginSdk\Contracts\Env as EnvContract;
use Bellows\Util\Value;
use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Env implements EnvContract
{
    protected array $parsed;

    public function __construct(
        protected string $raw,
    ) {
        $this->parsed = Dotenv::parse($this->raw);
    }

    public static function fromDir(string $dir): static
    {
        if (File::missing($dir . '/.env')) {
            throw new EnvMissing('No .env file found in ' . $dir);
        }

        return new static(
            File::get($dir . '/.env')
        );
    }

    public function get(string $key): ?string
    {
        return $this->parsed[$key] ?? null;
    }

    public function set($key, $value): void
    {
        $this->raw = $this->getUpdatedRaw($key, $value);

        $this->parsed = Dotenv::parse($this->raw);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function hasAll(...$keys): bool
    {
        return collect($keys)->every(fn ($key) => $this->has($key));
    }

    public function hasAny(...$keys): bool
    {
        return collect($keys)->some(fn ($key) => $this->has($key));
    }

    public function all(): array
    {
        return $this->parsed;
    }

    public function toString()
    {
        // Replace multiple newlines with just two
        $this->raw = preg_replace('/\n{3,}/', PHP_EOL . PHP_EOL, $this->raw);

        return $this->raw;
    }

    protected function getUpdatedRaw($key, $value): string
    {
        $value = Value::quoted(
            $value,
            Value::DOUBLE,
            fn ($value) => Str::contains($value, ['${', ' ']) || Str::contains($key, ['PASSWORD'])
        );

        if (Str::contains($this->raw, "{$key}=")) {
            return preg_replace("/{$key}=.*/", "{$key}={$value}", $this->raw);
        }

        // Look for other keys that are part of the same potential grouping
        $match = $this->getGroupingKeys($key)->map(
            fn ($gk) => Str::matchAll("/^{$gk}_[A-Z0-9_]+=.+/m", $this->raw)->last()
        )->filter()->first();

        if (!$match) {
            // Not part of any grouping, just tack it onto the end
            return $this->raw . PHP_EOL . PHP_EOL . "{$key}={$value}";
        }

        return Str::replaceFirst($match, "{$match}\n{$key}={$value}", $this->raw);
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

    public function __toString()
    {
        return $this->toString();
    }
}
