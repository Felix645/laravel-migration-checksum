<?php

namespace Neon\Migration\Checksum\Tests;

use Neon\Migration\Checksum\ChecksumServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ChecksumServiceProvider::class,
        ];
    }
}