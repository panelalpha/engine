<?php

namespace App\Console\Commands\Users;

use App\System;
use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class FixDomains extends Command
{
    use ResolvesProject;

    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['projects:fix-domains', 'users:fix-domains'];

    protected $signature = 'project:domain:fix {--project= : Project username} {--username= : Deprecated alias for --project} {--all}';

    protected $description = 'Create missing document-root directories for a project\'s domains';

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
            return $this->fixDomains([$user]);
        }

        $users = User::all();
        return $this->fixDomains($users);
    }

    /**
     * @param array<User>|Collection<int, User> $users
     */
    private function fixDomains($users): int
    {
        $system = new System();
        // $webserver = $system->getCurrentWebserver();
        $ok = true;
        foreach ($users as $user) {
            try {
                $this->output->write("Fixing domains for user '{$user->username}'...\n");
                foreach ($user->domains as $domain) {
                    $domain->projectDomain()->createDomainRootDir();
                }
                $this->info("  Finished fixing domains for the user.");
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $ok = false;
            }
        }
        $system->webserver()->reload();
        return (int)!$ok;
    }
}
