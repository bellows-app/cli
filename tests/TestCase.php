<?php

namespace Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function plugin(): PendingPlugin
    {
        return new PendingPlugin($this, $this->app, '', []);
    }
}
