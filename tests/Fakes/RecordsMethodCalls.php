<?php

namespace Tests\Fakes;

use Illuminate\Support\Collection;
use Illuminate\Testing\Assert;

trait RecordsMethodCalls
{
    protected Collection $recorded;

    public function assertMethodWasCalled($method, ...$args)
    {
        if (count($args) === 1 && is_callable($args[0])) {
            // Allow a callback to be passed to determine if the method was called with the correct args
            $match = $this->recorded->first(
                fn ($call) => $call['method'] === $method && $args[0]($call['args']),
            );
        } else {
            $match = $this->recorded->first(
                fn ($call) => $call['method'] === $method && $call['args'] === $args,
            );
        }

        Assert::assertNotNull($match);
    }

    protected function record($method, ...$args)
    {
        $this->recorded->push([
            'method' => $method,
            'args'   => $args,
        ]);
    }
}
