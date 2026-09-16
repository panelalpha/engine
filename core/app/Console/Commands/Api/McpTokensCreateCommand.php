<?php

namespace App\Console\Commands\Api;

use App\Mcp\ClientRegistration;
use App\Models\Admin;
use Illuminate\Console\Command;
use InvalidArgumentException;

class McpTokensCreateCommand extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['mcp-tokens:create'];

    protected $signature = 'mcp:token:create {name} {--s|short} {--no-register} {--client= : Print the registration command for one client only}';

    protected $description = 'Create an MCP token for the root admin';

    public function handle(): int
    {
        $root = Admin::rootAccount();
        $name = $this->argument('name');
        if (!is_string($name)) {
            $this->error("Invalid 'name' argument");
            return 1;
        }

        $client = $this->option('client');
        if ($client !== null && !is_string($client)) {
            $this->error("Invalid 'client' option");
            return 1;
        }

        $token = $root->createToken($name, ['mcp']);

        if ($this->option('short')) {
            $this->line($token->plainTextToken);
            return 0;
        }

        $this->info('MCP token created.');
        $this->warn("Save it — it won't be retrievable again.");
        $this->comment('=============================');
        $this->line($token->plainTextToken);
        $this->comment('=============================');

        if (!$this->option('no-register')) {
            try {
                $this->registrationHint($token->plainTextToken, $client);
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return 1;
            }
        }

        return 0;
    }

    /**
     * The token on its own is not enough to connect: a client also needs the
     * endpoint, the transport and - on a self-signed install - the CA. Print
     * the whole command, for every client, rather than leave the operator to
     * translate one client's argv into another's.
     */
    private function registrationHint(string $token, ?string $client): void
    {
        $url = rtrim((string) config('app.url'), '/') . '/mcp';

        $entries = $client === null || $client === ''
            ? ClientRegistration::all($url, $token)
            : [ClientRegistration::for($client, $url, $token)];

        $single = $client !== null && $client !== '';

        if (!$single) {
            $this->newLine();
            $this->info('Register the MCP server with your client:');
        }

        foreach ($entries as $entry) {
            $this->newLine();
            $this->line('<options=bold>' . $entry['label'] . '</>');
            foreach ($entry['lines'] as $line) {
                $this->line($line);
            }
        }

        if (!is_file('/etc/letsencrypt/live/panelalpha-engine-ip-cert/fullchain.pem')) {
            $this->newLine();
            $this->warn('The engine is using a self-signed certificate. Copy');
            $this->warn('/opt/panelalpha/shared-hosting/crt/server.cert to the client machine and trust it -');
            $this->warn('for the Node-based clients (Claude Code, Gemini CLI, VS Code, Cursor, Windsurf, Pi) export');
            $this->warn('NODE_EXTRA_CA_CERTS=/path/to/server.cert in every shell that starts them -');
            $this->warn('otherwise the server registers but fails to connect.');
        }

        $this->newLine();
        $this->line(sprintf('Verify the endpoint with: pae mcp:check %s', $token));
    }
}
