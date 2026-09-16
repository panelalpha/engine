<?php

namespace App\Console\Commands\Files;

use App\Console\Commands\Concerns\DispatchesApiRoute;
use Illuminate\Console\Command;

class FilesDownloadCommand extends Command
{
    use DispatchesApiRoute;

    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['files:download'];

    protected $signature = 'project:file:download
                            {project : Project username}
                            {--path= : File path inside the project}
                            {--out= : Local file to write (default: stdout)}';

    protected $description = 'Download a file from a project (GET /projects/{username}/files/download)';

    public function handle(): int
    {
        $project = (string) $this->argument('project');
        $path = (string) ($this->option('path') ?? '');
        $out = $this->option('out');

        if ($path === '') {
            $this->error('--path is required');
            return 1;
        }

        $response = $this->dispatchApiRoute(
            'GET',
            '/projects/' . rawurlencode($project) . '/files/download',
            ['path' => $path]
        );

        return $this->writeResponseBody($response, is_string($out) && $out !== '' ? $out : null);
    }
}
