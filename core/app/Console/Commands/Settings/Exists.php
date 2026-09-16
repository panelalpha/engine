<?php

namespace App\Console\Commands\Settings;
use Illuminate\Console\Command;
use App\Models\Setting;

class Exists extends Command
{
    protected $signature = 'settings:exists {name}';

    protected $description = 'Exits with code 0 if setting name exists in database';

    public function handle(): int
    {
        /** @var string */
        $name = $this->argument('name');

        return Setting::exists($name) ? 0 : 1;
    }
}
