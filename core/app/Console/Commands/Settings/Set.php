<?php

namespace App\Console\Commands\Settings;
use Illuminate\Console\Command;
use App\Models\Setting;

class Set extends Command
{
    protected $signature = 'settings:set {name} {value}';

    protected $description = 'Set system setting';

    public function handle(): int
    {
        /** @var string */
        $name = $this->argument('name');
        /** @var string */
        $value = $this->argument('value');

        try {
            Setting::set($name, $value);
            $this->info("`{$name}` set to `{$value}`");
        } catch (\Exception $e) {
            $this->error("[ERROR] " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
