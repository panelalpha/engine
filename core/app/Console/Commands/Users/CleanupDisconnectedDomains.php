<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CleanupDisconnectedDomains extends Command
{
    use ResolvesProject;

    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['projects:cleanup-disconnected-domains', 'users:cleanup-disconnected-domains'];

    protected $signature = 'project:domain:cleanup {--project= : Project username} {--username= : Deprecated alias for --project} {--all}';

    protected $description = 'Remove a project\'s config files for domains no longer in the database (per project; system:domain:cleanup does the same for webserver-level vhosts)';

    public function handle(): int
    {
        $this->foldProjectOption();

        /**
         * @var array{
         *   username: ?string,
         *   all: bool,
         * }
         */
        $options = $this->options();

        if (!$options['username'] && !$options['all']) {
            $this->error('One of following options is required: `--project=NAME` or `--all`');
            return 1;
        }

        // TODO also clean domains without user in DB
        if ($options['username']) {
            $user = User::findByUsername($options['username']);
            if (!$user) {
                $this->error('Invalid username');
                return 1;
            }
            return $this->cleanupDomains([$user]);
        }

        /** @var Collection<int, User> */
        $users = User::all();

        return $this->cleanupDomains($users);
    }

    /**
     * @param array<User>|Collection<int, User> $users
     */
    private function cleanupDomains($users): int
    {
        foreach ($users as $user) {
            $this->info("User `{$user->username}`:");

            $userProject = $user->project();
            $webDomains = $userProject->listDomains();
            $dbDomains = $user->domains;
            $dbDomainsList = $dbDomains->pluck('domain');
            $unusedDomains = [];
            foreach ($webDomains as $webDomain) {
                if (!($dbDomainsList->contains($webDomain))) {
                    $unusedDomains[] = $webDomain;
                }
            }

            if (empty($unusedDomains)) {
                $this->info('  Did not found any disconnected domains. Nothing to cleanup.');
                continue;
            }

            $this->info('  Found ' . count($unusedDomains) . ' disconnected domains: ');
            foreach ($unusedDomains as $unusedDomain) {
                $this->info('    ' . $unusedDomain);
            }
            if (!$this->confirm('  Continue with cleanup of these domains?')) {
                $this->info('  Skipping.');
                continue;
            }

            $userProject->deleteDomainsConfigs($unusedDomains);
            $userProject->reloadWebserver();

            $this->info('  Domains cleaned up.');
        }
        return 0;
    }
}
