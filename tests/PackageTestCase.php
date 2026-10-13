<?php

namespace KalnaLab\ZabbixAgent\Tests;

use Illuminate\Support\Facades\Http;
use KalnaLab\ZabbixAgent\ZabbixServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class PackageTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [ZabbixServiceProvider::class];
    }
}
