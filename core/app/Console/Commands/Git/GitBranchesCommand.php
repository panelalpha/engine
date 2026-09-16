<?php

namespace App\Console\Commands\Git;

use Illuminate\Console\Command;

class GitBranchesCommand extends Command
{
    use PrintsGitJson;

    protected $signature = 'git:branches
                            {username : Project username}
                            {--path= : Directory path inside the project (defaults to project (DinD) or public_html (FPM/LiteSpeed))}';

    protected $description = 'List git branches';

    public function handle(): int
    {
        $path = $this->resolvePath();

        return $this->dispatchGit('GET', '/git/branches', ['path' => $path]);
    }
}
