<?php

namespace Xul\AuthKit\Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithContainer;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Orchestra\Testbench\TestCase as Orchestra;
use Xul\AuthKit\AuthKitServiceProvider;

abstract class TestCase extends Orchestra
{
    use InteractsWithContainer;
    use MakesHttpRequests;

    protected function getPackageProviders($app): array
    {
        return [
            AuthKitServiceProvider::class,
        ];
    }
}