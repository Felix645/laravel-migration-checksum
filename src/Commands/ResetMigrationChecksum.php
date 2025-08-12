<?php

namespace Neon\Migration\Checksum\Commands;

use Illuminate\Console\Command;
use Neon\Migration\Checksum\Checksum;

class ResetMigrationChecksum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'neon:reset-migration-checksum';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets the migration checksums';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Checksum::reset();

        $this->info('Migration checksums were successfully reset.');

        return 0;
    }
}