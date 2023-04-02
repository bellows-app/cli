<?php

namespace Bellows;

use Illuminate\Support\Collection;

class PluginCommandRunner
{
    protected array $args = [];

    public function __construct(
        protected Collection $plugins,
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
        return $this->plugins->reduce(
            function ($carry, Plugin $plugin) use ($args) {
                $result = $plugin->{$this->methodToRun}($carry, ...array_splice($args, 1));

                if (is_array($result)) {
                    return array_merge($carry, $result);
                }

                return $result;
            },
            $args[0]
        );
    }

    public function run(): Collection
    {
        return $this->plugins->map(
            fn (Plugin $plugin) => $plugin->{$this->methodToRun}(...$this->args)
        );
    }
}
