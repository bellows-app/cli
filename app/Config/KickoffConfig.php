<?php

namespace Bellows\Config;

use Bellows\PluginSdk\Facades\Console;
use Bellows\Util\JsonSchema;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class KickoffConfig
{
    protected array $config;

    protected array $allConfigs = [];

    public function __construct(
        protected readonly string $path
    ) {
        if (File::missing($path)) {
            throw new InvalidArgumentException("Kickoff config file not found at {$path}");
        }

        $this->config = File::json($path);

        $this->load();
    }

    public function displayName(): string
    {
        $name = $this->name();
        $file = $this->file();

        return ($file === $name) ? $name : "{$name} ({$file})";
    }

    public function file(): string
    {
        return basename($this->path, '.json');
    }

    public function name(): string
    {
        return $this->config['name'] ?? $this->file();
    }

    public function all(): array
    {
        return $this->allConfigs;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function get(KickoffConfigKeys $key, mixed $default = null)
    {
        return $this->config[$key->value] ?? $default ?? KickoffConfigKeys::defaultValue($key);
    }

    public function merge(KickoffConfigKeys $key, array $toMerge)
    {
        $this->config[$key->value] = array_merge($this->get($key, []), $toMerge);
    }

    public function addPlugin(string $plugin): void
    {
        $key = KickoffConfigKeys::PLUGINS->value;

        $this->config[$key] ??= [];

        if (!in_array($plugin, $this->config[$key])) {
            $this->config[$key][] = $plugin;
        }
    }

    public function removePlugin(string $plugin): void
    {
        $this->config[KickoffConfigKeys::PLUGINS->value] = collect($this->config[KickoffConfigKeys::PLUGINS->value])
            ->reject(fn ($p) => $p === $plugin)
            ->values()
            ->toArray();
    }

    public function writeToFile(): void
    {
        File::put($this->path, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function isValid(): bool
    {
        $validator = new Validator();

        $validator->resolver()->registerFile(
            config('app.json_schemas.kickoff'),
            JsonSchema::cache(config('app.json_schemas.kickoff')),
        );

        $result = $validator->validate(
            // We need to re-encode and decode the config to make sure it's a an object
            json_decode(json_encode($this->config), false),
            config('app.json_schemas.kickoff'),
        );

        if ($result->hasError()) {
            Console::error('It looks like your config file has an error:');

            collect((new ErrorFormatter())->format($result->error()))->each(function ($errors, $key) {
                Console::newLine();

                Console::warn($key);

                collect($errors)->each(fn ($error) => Console::warn('  - ' . $error));

                Console::newLine();
            });

            return false;
        }

        return true;
    }

    protected function load(): void
    {
        // We need to make sure we're not recursively extending configs
        $usedConfigs = [$this->file()];

        $configExtends = $this->config['extends'] ?? false;

        while ($configExtends !== false) {
            if (in_array($configExtends, $usedConfigs)) {
                Console::error('Recursive config extending detected!');
                Console::warn(json_encode($usedConfigs, JSON_PRETTY_PRINT));
                exit;
            }

            $usedConfigs[] = $configExtends;

            $extendsConfigPath = BellowsConfig::getInstance()->kickoffConfigPath($configExtends . '.json');

            if (File::missing($extendsConfigPath)) {
                Console::error("Config extends file for \"{$configExtends}\" does not exist!");
                exit;
            }

            $extendsConfig = File::json($extendsConfigPath);

            $this->config = $this->mergeConfigs($extendsConfig);

            $configExtends = $extendsConfig['extends'] ?? false;
        }

        $this->allConfigs = $usedConfigs;
    }

    protected function mergeConfigs(array $toMerge)
    {
        $base = collect($this->config);
        $toMerge = collect($toMerge);

        $toMerge->each(function ($value, $key) use ($base) {
            if ($base->has($key)) {
                if (is_array($value)) {
                    $assoc = array_keys($value) !== range(0, count($value) - 1);

                    $newValue = array_merge($base->get($key), $value);

                    if (!$assoc) {
                        $newValue = array_values(array_unique($newValue));
                    }

                    // We're only interested in merge array values, keep the base config non-array values the same
                    $base->put($key, $newValue);
                }
            } else {
                $base->put($key, $value);
            }
        });

        return $base->toArray();
    }
}
