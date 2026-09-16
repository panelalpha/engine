<?php

namespace App\Console\Commands\Users;

use App\System;
use App\Models\User;
use App\Console\Commands\Concerns\ResolvesProject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class Rebuild extends Command
{
    use ResolvesProject;

    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['projects:rebuild', 'users:rebuild'];

    protected $signature = 'project:rebuild {--project= : Project username} {--username= : Deprecated alias for --project} {--all} {--wipe-vhosts-dir}';

    protected $description = 'Rebuild a project\'s docker compose stack and recreate its domain configuration files';

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
            return $this->rebuildUsers([$user]);
        }

        $users = User::all();
        return $this->rebuildUsers($users);
    }

    /**
     * @param array<User>|Collection<int, User> $users
     */
    private function rebuildUsers($users): int
    {
        $wipe = $this->option('wipe-vhosts-dir');

        $system = new System();
        $webserver = $system->webserver()->getCurrentWebserver();
        $ok = true;
        foreach ($users as $user) {
            try {
                $this->output->write("Rebuilding user '{$user->username}'...\n");
                $project = $user->project();
                if ($wipe) {
                    $project->deleteAllDomainsConfigs();
                }
                $workflow = $project->deployment();
                if ($workflow !== null) {
                    $workflow->rebuildFromSource();
                } else {
                    $project->prepareLinuxIsolation();
                    $project->recreateOuterCompose();
                }
                $user->save();
                if (count($user->domains)) {
                    $project->rebuildDomains();
                    $project->waitForAllRunning();
                    $project->reloadWebserver();
                }
                $this->info("  Finished rebuilding user.");
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $ok = false;
            }
        }
        if ($wipe) {
            try {
                $dir = $system->engineDirPath() . "/webserver-config/{$webserver}/vhosts";
                $system->exec("rm -rf {$dir}/* {$dir}/.*");
            } catch (\Exception $e) {
            }
        }
        $system->webserver()->rebuildDomains();
        return (int)!$ok;
    }
}
