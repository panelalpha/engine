<?php

namespace App\Console\Commands\Users;

use App\System;
use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class AddMissingWwwDomainAliases extends Command
{
    use ResolvesProject;

    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['projects:add-missing-www-domain-aliases', 'users:add-missing-www-domain-aliases'];

    protected $signature = 'project:domain:add-www-alias {--project= : Project username} {--username= : Deprecated alias for --project} {--all}';

    protected $description = 'Add missing www. aliases to a project\'s domains';

    public function handle(): int
    {
        $this->foldProjectOption();

        /** @var string */
        $username = $this->option('username');
        /** @var bool */
        $all = $this->option('all');

        if (!$username && !$all) {
            $this->error('One of following options is required: `--project=NAME` or `--all`');
            return 1;
        }

        if ($username) {
            $user = User::findByUsername($username);
            if (!$user) {
                $this->error('Invalid username');
                return 1;
            }
            return $this->addAliases([$user]);
        }

        $users = User::all();
        return $this->addAliases($users);
    }

    /**
     * @param array<User>|Collection<int, User> $users
     */
    private function addAliases($users): int
    {
        $system = new System();
        $ok = true;
        foreach ($users as $user) {
            try {
                $this->output->write("Adding www. aliases for user '{$user->username}'...\n");
                foreach ($user->domains as $domain) {
                    $this->info("  Checking domain {$domain->domain}...");
                    if ($domain->type === 'sub') {
                        $this->comment("    It is subdomain, skipping.");
                        continue;
                    }
                    $aliases = $domain->getAliases();
                    if (in_array('www.' . $domain->domain, $aliases)) {
                        $this->comment("    It already has www. alias, skipping.");
                        continue;
                    }
                    if ($found = $domain->findOtherDomainByNameOrAlias('www.' . $domain->domain)) {
                        if ($found->domain == 'www.' . $domain->domain) {
                            $this->comment("    It already exists as {$found->type} domain under user {$found->getUser()->username}, skipping.");
                            continue;
                        }
                        $this->comment("    It already exists as alias of {$found->domain} domain under user {$found->getUser()->username}, skipping.");
                        continue;
                    }
                    $this->info("    Adding www. alias and rebuilding domain...");
                    $domain->addAlias('www.' . $domain->domain);
                    $domain->projectDomain()->rebuild();
                    $domain->save();
                    $this->info("    Domain rebuilt.");
                }
                $this->info("  Finished.");
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $ok = false;
            }
        }
        $system->webserver()->reload();
        return (int)!$ok;
    }
}
