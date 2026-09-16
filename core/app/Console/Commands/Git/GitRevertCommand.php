<?php

namespace App\Console\Commands\Git;

use Illuminate\Console\Command;

class GitRevertCommand extends Command
{
    use PrintsGitJson;

    protected $signature = 'git:revert
                            {username : Project username}
                            {--path= : Directory path inside the project (defaults to project (DinD) or public_html (FPM/LiteSpeed))}
                            {--ref= : Commit ref to revert to (default HEAD)}';

    protected $description = 'Revert local git changes';

    public function handle(): int
    {
        $path = $this->resolvePath();

        $params = ['path' => $path];
        $ref = (string) ($this->option('ref') ?? '');
        if ($ref !== '') {
            $params['ref'] = $ref;
        }

        return $this->dispatchGit('POST', '/git/revert', $params);
    }
}
