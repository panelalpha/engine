<?php

namespace App\Console\Commands\Git;

use Illuminate\Console\Command;

class GitChangeBranchCommand extends Command
{
    use PrintsGitJson;

    protected $signature = 'git:change-branch
                            {username : Project username}
                            {--path= : Directory path inside the project (defaults to project (DinD) or public_html (FPM/LiteSpeed))}
                            {--branch= : Branch to switch to}';

    protected $description = 'Change the tracked git branch';

    public function handle(): int
    {
        $path = $this->resolvePath();

        $branch = (string) ($this->option('branch') ?? '');
        if ($branch === '') {
            $this->error('--branch is required');

            return 1;
        }

        return $this->dispatchGit('PUT', '/git/change-branch', [
            'path' => $path,
            'branch' => $branch,
        ]);
    }
}
