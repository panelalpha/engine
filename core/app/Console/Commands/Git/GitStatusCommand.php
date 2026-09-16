<?php

namespace App\Console\Commands\Git;

use Illuminate\Console\Command;

class GitStatusCommand extends Command
{
    use PrintsGitJson;

    protected $signature = 'git:status
                            {username : Project username}
                            {--path= : Directory path inside the project (defaults to project (DinD) or public_html (FPM/LiteSpeed))}
                            {--fetch : Fetch from remote before reporting status}';

    protected $description = 'Git repository status';

    public function handle(): int
    {
        $path = $this->resolvePath();

        $params = ['path' => $path];
        if ($this->option('fetch')) {
            $params['fetch'] = '1';
        }

        return $this->dispatchGit('GET', '/git/status', $params);
    }
}
