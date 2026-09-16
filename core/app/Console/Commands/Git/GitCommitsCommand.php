<?php

namespace App\Console\Commands\Git;

use Illuminate\Console\Command;

class GitCommitsCommand extends Command
{
    use PrintsGitJson;

    protected $signature = 'git:commits
                            {username : Project username}
                            {--path= : Directory path inside the project (defaults to project (DinD) or public_html (FPM/LiteSpeed))}
                            {--branch= : Branch to list commits from}
                            {--limit=50 : Maximum number of commits}';

    protected $description = 'List git commits';

    public function handle(): int
    {
        $path = $this->resolvePath();

        $params = ['path' => $path, 'limit' => (string) $this->option('limit')];
        $branch = (string) ($this->option('branch') ?? '');
        if ($branch !== '') {
            $params['branch'] = $branch;
        }

        return $this->dispatchGit('GET', '/git/commits', $params);
    }
}
