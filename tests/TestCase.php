<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use KraenzleRitter\FormatPolicy\FormatPolicyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FormatPolicyServiceProvider::class];
    }
}
