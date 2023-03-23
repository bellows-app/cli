<?php

namespace Tests;

use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Http::preventStrayRequests();
    }

    public function plugin(): PendingPlugin
    {
        return new PendingPlugin($this, $this->app, '', []);
    }
}
