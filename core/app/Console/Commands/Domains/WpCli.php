<?php

namespace App\Console\Commands\Domains;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WpCli extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['domains:wp-cli'];

    protected $signature = 'domain:wp-cli {domain} {arg*}';

    protected $description = "Runs WP-CLI command as domain's user, using the domain's document root as --path if not provided.";

    public function handle(): int
    {
        $domainName = $this->argument('domain');
        assert(is_string($domainName));
        $domain = Domain::findByName($domainName);
        if (!$domain) {
            $this->error("Domain `{$domainName}` not found in database.");
            return 1;
        }

        $user = $domain->user;
        if (!$user) {
            $this->error("User #{$domain->user_id} of domain `{$domainName}` not found in database.");
            return 1;
        }

        $args = $this->argument('arg');
        assert(is_array($args));
        $wpCliArgs = [];
        $addPath = true;
        foreach ($args as $arg) {
            assert(is_string($arg));
            if (Str::startsWith($arg, '--path=')) {
                $addPath = false;
            }
            $wpCliArgs[] = $arg;
        }

        if ($addPath) {
            $domainRootDir = $user->project()->homeDirPath() . $domain->getDocumentRoot();
            $wpCliArgs[] = "--path=" . $domainRootDir;
        }

        $result = $user->project()->runWpCli($wpCliArgs);

        $this->line("stdout:");
        $this->info(trim($result['stdout']));
        $this->line("stderr:");
        $this->warn(trim($result['stderr']));
        $this->line("exit_code: " . $result['exit_code']);

        return 0;
    }
}
