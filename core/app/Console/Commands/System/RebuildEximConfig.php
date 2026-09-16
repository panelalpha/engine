<?php

namespace App\Console\Commands\System;

use App\System;
use Illuminate\Console\Command;

class RebuildEximConfig extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['system:rebuild-exim-config'];

    protected $signature = 'system:exim:rebuild';

    protected $description = 'Recreate configuration files for exim4 from database.';

    public function handle(): int
    {
        try {
            $system = new System();
            $system->exim()->rebuildEximConfig();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        return 0;
    }
}
