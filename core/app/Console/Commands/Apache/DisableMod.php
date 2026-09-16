<?php

namespace App\Console\Commands\Apache;

use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class DisableMod extends Command
{
    use ResolvesProject;

    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['apache:disable-mod'];

    protected $signature = 'apache:mod:disable {mod} {--project= : Project username} {--username= : Deprecated alias for --project} {--all}';

    protected $description = 'Disable an Apache module for a project (or --all projects)';

    public function handle(): int
    {
        $this->foldProjectOption();

        /** @var string */
        $mod = $this->argument('mod');
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
            return $this->disableModForUsers($mod, [$user]);
        }

        $users = User::all();
        return $this->disableModForUsers($mod, $users);
    }

    /**
     * @param string $mod
     * @param array<User>|Collection<int, User> $users
     */
    private function disableModForUsers($mod, $users): int
    {
        foreach ($users as $user) {
            try {
                $this->output->write("Disabling mod '{$mod}' for user '{$user->username}'...\n");
                $user->project()->disableApacheMod($mod);
                $user->project()->reloadApache();
                $this->info("  Mod disabled.");
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        return 0;
    }
}
