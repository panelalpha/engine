<?php

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

use App\Mcp\ClientRegistration;
use Illuminate\Support\Facades\Artisan;

// `pae mcp:connect:<agent>`, one per agent, so `pae mcp:connect` can list exact commands.
foreach (ClientRegistration::all('', '') as $client => $entry) {
    Artisan::command("mcp:connect:{$client} {--bare : Print only the command, for scripts}", function () use ($client) {
        return $this->call('mcp:connect', ['client' => $client, '--bare' => (bool) $this->option('bare')]);
    })->purpose("Mint an MCP token and print the {$entry['label']} command");
}
