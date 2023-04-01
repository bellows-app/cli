<?php

namespace Tests;

abstract class DuskyTestCase extends TestCase
{
    public function command(string $command)
    {
        return new DuskyCommand($command);
    }
}
