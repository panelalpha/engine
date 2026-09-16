<?php

namespace App\Console\Commands\Git;

use Illuminate\Console\Command;

class GitDisconnectCommand extends Command
{
    use PrintsGitJson;

    protected $signature = 'git:disconnect
                            {username : Project username}
                            {--path= : Directory path inside the project (defaults to project (DinD) or public_html (FPM/LiteSpeed))}';

    protected $description = 'Disconnect git from a directory';

    public function handle(): int
    {
        $path = $this->resolvePath();

        return $this->dispatchGit('POST', '/git/disconnect', ['path' => $path]);
    }
}
