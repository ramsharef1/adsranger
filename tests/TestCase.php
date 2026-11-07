<?php

namespace Tests;

use App\Support\Webhooks;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Webhooks::$resolver = null; // static test hook must not leak between tests
        parent::tearDown();
    }
}
