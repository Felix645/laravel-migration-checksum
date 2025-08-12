<?php

namespace Neon\Migration\Checksum\Traits;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Neon\Migration\Checksum\Checksum;

trait ChecksForChecksum
{
    use DatabaseMigrations {
        runDatabaseMigrations as baseRunDatabaseMigrations;
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getChecksumConfiguration(): array
    {
        return [];
    }

    public function runDatabaseMigrations(): void
    {
        $config = $this->getChecksumConfiguration();

        if (empty($config)) {
            $this->baseRunDatabaseMigrations();
            return;
        }

        foreach ($config as $key => $key_config) {
            $new_migrations = Checksum::dir($key)->hasNewMigrations();

            if ($new_migrations) {
                $this->artisan('migrate:fresh', [
                    '--path' => $key_config['--path'],
                    '--database' => $key_config['--database'],
                ]);

                Checksum::dir($key)->updateChecksum();
            }
        }

        $this->app[Kernel::class]->setArtisan(null);

        $this->beforeApplicationDestroyed(function () use ($config) {
            foreach ($config as $key => $key_config) {
                Checksum::dir($key)->truncateTables();
            }
        });
    }
}