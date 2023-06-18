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

    public function get(KickoffConfigKeys $key, mixed $default = null)
    {
        return $this->config[$key->value] ?? $default ?? KickoffConfigKeys::defaultValue($key);
    }

    public function merge(KickoffConfigKeys $key, array $toMerge)
    {
        $this->config[$key->value] = array_merge($this->get($key, []), $toMerge);
    }

    public function isValid(): bool
    {
        $validator = new Validator();

        $validator->resolver()->registerFile(
            config('app.json_schemas.kickoff'),
            JsonSchema::cache(config('app.json_schemas.kickoff')),
        );

        $result = $validator->validate(
            (object) $this->config,
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
                    // We're only interested in merge array values, keep the base config non-array values the same
                    $base->put($key, array_merge($base->get($key), $value));
                }
            } else {
                $base->put($key, $value);
            }
        });

        return $base->toArray();
    }
}
