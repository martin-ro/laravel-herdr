<?php

namespace Herdr\Tests;

use Herdr\Facades\Herdr;
use Herdr\HerdrServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [HerdrServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Herdr' => Herdr::class];
    }
}
