<?php

namespace Tests;

use Illuminate\Testing\PendingCommand;
use Mockery\MockInterface;

class PendingPlugin extends PendingCommand
{
    protected MockInterface $serverMock;

    protected MockInterface $siteMock;

    public function setup(): static
    {
        $this->hasExecuted = true;

        $this->mockConsoleOutput();

        return $this;
    }

    public function validate()
    {
        $this->verifyExpectations();
        $this->flushExpectations();
    }

    public function __destruct()
    {
        // Override this so the `run` method never fires
    }
}
