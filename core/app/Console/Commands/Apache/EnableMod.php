<?php

namespace App\Console\Commands\Apache;

use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class EnableMod extends Command
{
    use ResolvesProject;

    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['apache:enable-mod'];

    protected $signature = 'apache:mod:enable {mod} {--project= : Project username} {--username= : Deprecated alias for --project} {--all}';

    protected $description = 'Enable an Apache module for a project (or --all projects)';

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
            return $this->enableModForUsers($mod, [$user]);
        }

        $users = User::all();
        return $this->enableModForUsers($mod, $users);
    }

    /**
     * @param string $mod
     * @param array<User>|Collection<int, User> $users
     */
    private function enableModForUsers($mod, $users): int
    {
        foreach ($users as $user) {
            try {
                $this->output->write("Enabling mod '{$mod}' for user '{$user->username}'...\n");
                $user->project()->enableApacheMod($mod);
                $user->project()->reloadApache();
                $this->info("  Mod enabled.");
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        return 0;
    }
}
