<?php

namespace App\Console\Commands\System;

use App\Models\ProxyRule;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class ProxyRuleCreateCommand extends Command
{
    use ResolvesProject;

    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['proxy-rule:create'];

    protected $signature = 'proxy:rule:create
        {--transport= : Transport type (http, tcp, udp) - interactive if not provided}
        {--listen-port= : Listen port - interactive if not provided}
        {--listen-ip=* : Listen IP (default: *)}
        {--server-name= : Server name/hostname (for HTTP only)}
        {--upstream-host= : Upstream host - interactive if not provided}
        {--upstream-port= : Upstream port - interactive if not provided}
        {--upstream-protocol=http : Upstream protocol (for HTTP: http, https; for stream: leave empty)}
        {--scope=system : Owner scope (system or user)}
        {--project= : Username (required for user-owned rules)} {--username= : Deprecated alias for --project}
        {--force : Skip confirmation}';

    protected function getListenIp(): string
    {
        $listenIp = $this->option('listen-ip');
        if (is_string($listenIp)) {
            return $listenIp;
        }
        return '*';
    }

    protected $description = 'Create a new proxy rule';

    public function handle(): int
    {
        $this->foldProjectOption();

        // Collect input
        $scope = $this->option('scope');
        if (!in_array($scope, ['system', 'user'])) {
            $this->error("Scope must be 'system' or 'user'.");
            return 1;
        }
        assert(is_string($scope));

        $username = $this->option('username');
        if ($scope === 'user' && !is_string($username)) {
            /** @var mixed $username */
            $username = $this->ask('Username (for user-owned rule)');
        }
        assert(is_string($username));

        $transport = $this->option('transport') ?: $this->choice(
            'Transport type',
            ['http', 'tcp', 'udp'],
            0
        );
        assert(is_string($transport));

        $listenPort = $this->option('listen-port') ?: $this->ask('Listen port (1-65535)');
        if (!is_numeric($listenPort) || (int)$listenPort < 1 || (int)$listenPort > 65535) {
            $this->error('Invalid port number.');
            return 1;
        }
        $listenPort = (int)$listenPort;

        $listenIp = $this->getListenIp();

        $serverName = null;
        if ($transport === 'http') {
            $serverNameOption = $this->option('server-name');
            if (!empty($serverNameOption) && is_string($serverNameOption)) {
                $serverName = $serverNameOption;
            }
            if (empty($serverName)) {
                /** @var mixed $serverNameAsk */
                $serverNameAsk = $this->ask('Server name/hostname (or leave blank for wildcard)');
                if (!empty($serverNameAsk) && is_string($serverNameAsk)) {
                    $serverName = $serverNameAsk;
                }
            }
        }

        $upstreamHost = $this->option('upstream-host');
        if (empty($upstreamHost) || !is_string($upstreamHost)) {
            /** @var mixed */
            $upstreamHostAsk = $this->ask('Upstream host');
            if (!empty($upstreamHostAsk) && is_string($upstreamHostAsk)) {
                $upstreamHost = $upstreamHostAsk;
            }
        }
        if (!is_string($upstreamHost)) {
            $this->error('Invalid upstream host.');
            return 1;
        }

        $upstreamPort = $this->option('upstream-port') ?: $this->ask('Upstream port (1-65535)');
        if (!is_numeric($upstreamPort) || (int)$upstreamPort < 1 || (int)$upstreamPort > 65535) {
            $this->error('Invalid upstream port number.');
            return 1;
        }
        $upstreamPort = (int)$upstreamPort;

        $upstreamProtocol = null;
        if ($transport === 'http') {
            $upstreamProtocolOption = $this->option('upstream-protocol');
            if (!empty($upstreamProtocolOption) && is_string($upstreamProtocolOption)) {
                $upstreamProtocol = $upstreamProtocolOption;
            }
        }

        // Display summary
        $this->info("\nNew Proxy Rule Summary:");
        $this->line("  Owner Scope: $scope" . ($username ? " ({$username})" : ''));
        $this->line("  Transport: $transport");
        $this->line("  Listen: {$listenIp}:{$listenPort}" . ($serverName ? " [{$serverName}]" : ''));
        $this->line("  Upstream: {$upstreamHost}:{$upstreamPort}" . ($upstreamProtocol ? " ({$upstreamProtocol})" : ''));

        if (!$this->option('force') && !$this->confirm('Create this rule?')) {
            $this->info('Cancelled.');
            return 0;
        }

        // Check for duplicates

        $existing = ProxyRule::query();
        $existing->where('transport', $transport)
            ->where('listen_port', $listenPort)
            ->where('listen_ip', $listenIp)
            ->where('owner_scope', $scope);

        if ($username) {
            $existing->where('username', $username);
        } else {
            $existing->whereNull('username');
        }

        if ($transport === 'http' && $serverName) {
            $existing->where('server_name', $serverName);
        } elseif ($transport === 'http') {
            $existing->whereNull('server_name');
        }

        if ($existing->first()) {
            $this->error('A rule with this configuration already exists.');
            return 1;
        }

        // Create the rule
        /** @var ProxyRule */
        $rule = ProxyRule::create([
            'owner_scope' => $scope,
            'username' => $username,
            'transport' => $transport,
            'listen_ip' => $listenIp,
            'listen_port' => $listenPort,
            'server_name' => $serverName,
            'upstream_host' => $upstreamHost,
            'upstream_port' => $upstreamPort,
            'upstream_protocol' => $upstreamProtocol,
            'enabled' => true,
            'is_generated' => false,
            'metadata' => ['created_via' => 'artisan-command'],
        ]);

        $this->info("Rule created successfully (ID: {$rule->id})");
        return 0;
    }
}
