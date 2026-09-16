<?php

namespace App\Console\Commands\Users;

use App\System;
use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RebuildDomains extends Command
{
    use ResolvesProject;

    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['projects:rebuild-domains', 'users:rebuild-domains'];

    protected $signature = 'project:domain:rebuild {--project= : Project username} {--username= : Deprecated alias for --project} {--all}';

    protected $description = 'Recreate the webserver configuration for a project\'s domains';

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
            return $this->rebuildDomains([$user]);
        }

        $users = User::all();
        return $this->rebuildDomains($users);
    }

    /**
     * @param array<User>|Collection<int, User> $users
     */
    private function rebuildDomains($users): int
    {
        $system = new System();
        $ok = true;
        foreach ($users as $user) {
            try {
                $this->output->write("Rebuilding domains for user '{$user->username}'...\n");
                foreach ($user->domains as $domain) {
                    $this->info("  Rebuilding domain '{$domain->domain}'...");
                    $domain->projectDomain()->rebuild();
                }
                $user->project()->reloadWebserver();
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
