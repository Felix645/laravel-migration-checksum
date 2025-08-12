<?php

namespace Neon\Migration\Checksum;

use Illuminate\Support\ServiceProvider;
use Neon\Migration\Checksum\Commands\ResetMigrationChecksum;

class ChecksumServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ResetMigrationChecksum::class,
            ]);
        }
    }
}