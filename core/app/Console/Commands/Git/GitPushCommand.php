<?php

namespace App\Console\Commands\Git;

use Illuminate\Console\Command;

class GitPushCommand extends Command
{
    use PrintsGitJson;

    protected $signature = 'git:push
                            {username : Project username}
                            {--path= : Directory path inside the project (defaults to project (DinD) or public_html (FPM/LiteSpeed))}';

    protected $description = 'Push local git changes';

    public function handle(): int
    {
        $path = $this->resolvePath();

        return $this->dispatchGit('POST', '/git/push', ['path' => $path]);
    }
}
