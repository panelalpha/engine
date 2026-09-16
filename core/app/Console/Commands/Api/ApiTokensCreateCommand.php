<?php

namespace App\Console\Commands\Api;

use App\Models\Admin;
use Illuminate\Console\Command;

class ApiTokensCreateCommand extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['api-tokens:create'];

    protected $signature = 'api:token:create {name} {--s|short}';

    protected $description = 'Create an API token for the root admin';

    public function handle(): int
    {
        $root = Admin::rootAccount();
        $name = $this->argument('name');
        if (!is_string($name)) {
            $this->error("Invalid 'name' argument");
            return 1;
        }
        $token = $root->createToken($name);

        if ($this->option('short')) {
            $this->line($token->plainTextToken);
            return 0;
        }

        $this->info('Api token created.');
        $this->warn('Save it, it won\'t be retrieveable again.');
        $this->comment('=============================');
        $this->line($token->plainTextToken);
        $this->comment('=============================');
        return 0;
    }
}