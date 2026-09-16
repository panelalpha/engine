<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDatabaseConnection extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['system:test-database-connection'];

    protected $signature = 'system:database:test {--retries=0}';

    protected $description = 'Test if mysql database is ready.';

    public function handle(): int
    {
        $retries = $this->option('retries');
        assert(is_string($retries));
        $retries = (int)$retries;
        if ($retries < 0) {
            $retries = 0;
        }
        do {
            try {
                DB::statement("SELECT 1 FROM `information_schema`.`tables` LIMIT 1");
                $this->line("Test successful");
                return 0;
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $retries && sleep(5);
        } while ($retries--);
        return 1;
    }
}
