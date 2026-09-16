<?php

namespace App\Console\Commands\Domains;

use App\Console\Commands\Concerns\ResolvesProject;
use App\System;
use App\Lib\Helpers\UpstreamSpec;
use App\Models\Domain;
use App\Models\ProxyRule;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\Table;

class DomainsCreate extends Command
{
    use ResolvesProject;

    protected $aliases = ['domains:create', 'projects:domains:create'];

    protected $signature = 'domain:create
        {domain? : Domain name to create}
        {--domain= : Domain name (alternative to the positional argument)}
        {--project= : Project username that will own the domain}
        {--username= : Deprecated alias for --project}
        {--type=addon : Domain type: addon or sub}
        {--parent-domain= : Parent domain (required for type=sub)}
        {--alias=* : Optional alias hostname(s)}
        {--proxy-to= : Optional upstream (port or host:port); creates ProxyRules for 80/443}
        {--no-ssl : Disable SSL for this domain}
        {--force : Skip confirmation}';

    protected $description = 'Create an addon or subdomain under an existing project';

    public function handle(): int
    {
        $this->foldProjectOption();

        $positional = strtolower(trim((string) ($this->argument('domain') ?? '')));
        $optionDomain = strtolower(trim((string) ($this->option('domain') ?? '')));
        if ($positional !== '' && $optionDomain !== '' && $positional !== $optionDomain) {
            $this->error("Conflicting domain names: argument '{$positional}' vs --domain='{$optionDomain}'.");
            return 1;
        }
        $domainName = $positional !== '' ? $positional : $optionDomain;
        $project = trim((string) $this->option('username'));
        $type = strtolower(trim((string) $this->option('type')));
        $parentDomain = strtolower(trim((string) $this->option('parent-domain')));
        $noSsl = (bool) $this->option('no-ssl');
        $proxyToRaw = trim((string) ($this->option('proxy-to') ?? ''));

        if ($domainName === '') {
            $this->error('Domain name is required (positional argument or --domain).');
            return 1;
        }
        if ($project === '') {
            $this->error('--project is required.');
            return 1;
        }

        if ($type === 'subdomain') {
            $type = 'sub';
        }
        if (!in_array($type, ['addon', 'sub'], true)) {
            $this->error("--type must be 'addon' or 'sub'.");
            return 1;
        }

        $aliases = [];
        $rawAliases = $this->option('alias');
        if (is_array($rawAliases)) {
            foreach ($rawAliases as $alias) {
                if (!is_string($alias) || trim($alias) === '') {
                    continue;
                }
                $aliases[] = strtolower(trim($alias));
            }
        }
        $aliases = array_values(array_unique($aliases));

        // Match DomainController::store — www.* becomes primary without www + alias.
        if (Str::startsWith($domainName, 'www.')) {
            if (!in_array($domainName, $aliases, true)) {
                $aliases[] = $domainName;
            }
            $domainName = Str::after($domainName, 'www.');
        }

        $user = User::findByUsername($project);
        if (!$user) {
            $this->error("Project '{$project}' not found.");
            return 1;
        }

        $upstreamHost = null;
        $upstreamPort = null;
        if ($proxyToRaw !== '') {
            try {
                [$upstreamHost, $upstreamPort] = UpstreamSpec::parse($proxyToRaw, $user->username);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return 1;
            }
        }

        if ($type === 'addon') {
            $limit = $user->getAddonDomainsLimit();
            if ($limit !== null) {
                $count = $user->domains()->getQuery()->where('type', 'addon')->count();
                if ($limit <= $count) {
                    $this->error("Addon domains limit of {$limit} reached.");
                    return 1;
                }
            }
        } else {
            $limit = $user->getSubdomainsLimit();
            if ($limit !== null) {
                $count = $user->domains()->getQuery()->where('type', 'sub')->count();
                if ($limit <= $count) {
                    $this->error("Subdomains limit of {$limit} reached.");
                    return 1;
                }
            }
            if ($parentDomain === '') {
                $this->error('--parent-domain is required when --type=sub.');
                return 1;
            }
            if (!$user->domains()->getQuery()->where('domain', $parentDomain)->exists()) {
                $this->error("Parent domain '{$parentDomain}' not found for project '{$project}'.");
                return 1;
            }
            if (!Str::endsWith($domainName, $parentDomain)) {
                $this->error("Domain '{$domainName}' must end with parent domain '{$parentDomain}'.");
                return 1;
            }
        }

        if (!filter_var($domainName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $this->error("Invalid domain name '{$domainName}'.");
            return 1;
        }
        if (Domain::domainOrAliasExists($domainName)) {
            $this->error("Domain '{$domainName}' already exists.");
            return 1;
        }

        foreach ($aliases as $alias) {
            if (!filter_var($alias, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $this->error("Invalid alias '{$alias}'.");
                return 1;
            }
            if (Domain::domainOrAliasExists($alias)) {
                $this->error("Alias '{$alias}' already exists.");
                return 1;
            }
        }

        $docRoot = "/{$domainName}/public_html";
        $proxyLabel = ($upstreamHost !== null && $upstreamPort !== null)
            ? "{$upstreamHost}:{$upstreamPort}"
            : '-';

        $this->line('');
        $this->info('Create domain');
        $table = new Table($this->output);
        $table->setRows([
            ['Domain', $domainName],
            ['Project', $user->username],
            ['Type', $type],
            ['Parent domain', $type === 'sub' ? $parentDomain : '-'],
            ['Aliases', $aliases === [] ? '-' : implode("\n", $aliases)],
            ['SSL', $noSsl ? 'disabled' : 'enabled'],
            ['Document root', $docRoot],
            ['Proxy to', $proxyLabel],
        ]);
        $table->render();

        if (!$this->option('force') && !$this->confirm('Create this domain?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $details = [
            'document_root' => $docRoot,
            'redirect_enabled' => false,
            'redirect_url' => null,
            'force_https_redirect' => false,
            'ssl_disabled' => $noSsl,
            'aliases' => $aliases,
        ];
        if ($type === 'sub') {
            $details['parent_domain'] = $parentDomain;
        }

        /** @var Domain $domain */
        $domain = Domain::create([
            'user_id' => $user->id,
            'domain' => $domainName,
            'type' => $type,
            'details' => $details,
        ]);

        try {
            $domain->projectDomain()->create();
            $user->project()->syncPhpHandlersScripts();
            $user->project()->runEntrypointScriptsSync();

            if ($upstreamHost !== null && $upstreamPort !== null) {
                foreach ([80, 443] as $listenPort) {
                    ProxyRule::updateOrCreate(
                        [
                            'owner_scope' => 'user',
                            'username' => $user->username,
                            'transport' => 'http',
                            'listen_port' => $listenPort,
                            'server_name' => $domainName,
                        ],
                        [
                            'enabled' => true,
                            'listen_ip' => '*',
                            'upstream_host' => $upstreamHost,
                            'upstream_port' => $upstreamPort,
                            'upstream_protocol' => 'http',
                            'is_generated' => false,
                            'metadata' => [
                                'source' => 'domain-create',
                                'description' => "{$listenPort}→{$upstreamPort} for {$domainName}",
                            ],
                        ]
                    );
                }
                $domain->projectDomain()->rebuild();
                (new System())->webserver()->scheduleWebserverReloadInBackground();
            }
        } catch (\Throwable $e) {
            try {
                $domain->delete();
            } catch (\Throwable $cleanupError) {
                // best-effort
            }
            $this->error('Domain create failed: ' . $e->getMessage());
            return 1;
        }

        $this->info("Domain '{$domainName}' created.");
        if ($upstreamHost !== null) {
            $this->line("Proxy rules set → {$upstreamHost}:{$upstreamPort} (listen 80/443).");
        }
        $this->line('');

        return 0;
    }
}
