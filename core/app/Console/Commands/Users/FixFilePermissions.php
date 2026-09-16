<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class FixFilePermissions extends Command
{
    use ResolvesProject;

    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['projects:fix-file-permissions', 'users:fix-file-permissions'];

    protected $signature = 'project:permission:fix {--project= : Project username} {--username= : Deprecated alias for --project} {--all}';

    protected $description = 'Fix file permissions under a project\'s home directory';

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
            return $this->fix([$user]);
        }

        $users = User::all();
        return $this->fix($users);
    }

    /**
     * @param array<User>|Collection<int, User> $users
     */
    private function fix($users): int
    {
        $ok = true;
        foreach ($users as $user) {
            try {
                $this->output->write("Fixing '{$user->username}'...\n");
                $user->project()->fixPermissions();
                $this->info("  Finished.");
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $ok = false;
            }
        }
        return (int)!$ok;
    }
}
