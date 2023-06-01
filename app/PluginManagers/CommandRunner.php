<?php

namespace Bellows\PluginManagers;

use Illuminate\Support\Collection;

class CommandRunner
{
    protected array $args = [];

    public function __construct(
        protected Collection $pluginResults,
        protected string $methodToRun,
    ) {
    }

    public function withArgs(...$args): static
    {
        $this->args = $args;

        return $this;
    }

    public function reduce(...$args)
    {
        $reduceArgs = array_splice($args, 1);

        return $this->pluginResults->reduce(
            function ($carry, $plugin) use ($reduceArgs) {
                $result = $plugin->{$this->methodToRun}($carry, ...$reduceArgs);

                return (is_array($result)) ? array_merge($carry, $result) : $result;
            },
            $args[0]
        );
    }

    public function run(): Collection
    {
        return $this->pluginResults->map(
            fn ($plugin) => $plugin->{$this->methodToRun}(...$this->args)
        );
    }
}
