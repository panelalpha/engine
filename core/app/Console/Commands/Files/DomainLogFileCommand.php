<?php

namespace App\Console\Commands\Files;

use App\Console\Commands\Concerns\DispatchesApiRoute;
use Illuminate\Console\Command;

class DomainLogFileCommand extends Command
{
    use DispatchesApiRoute;

    /** Older spellings still answer, so nothing scripted against them breaks. */
    protected $aliases = ['domain:log:show', 'domains:log-file'];

    protected $signature = 'project:domain:log
                            {project : Project username}
                            {domain : Domain the log belongs to}
                            {filename? : Log file to fetch; omit to list what is available}
                            {--all-webservers : Include log files from every webserver, not just the active one}
                            {--out= : Local file to write (default: stdout)}';

    protected $description = 'List or download a domain log file (GET /projects/{username}/domains/{domain}/log-files)';

    public function handle(): int
    {
        $project = rawurlencode((string) $this->argument('project'));
        $domain = rawurlencode((string) $this->argument('domain'));
        $filename = $this->argument('filename');
        $out = $this->option('out');

        $params = [];
        if ($this->option('all-webservers')) {
            $params['all_webservers'] = 1;
        }

        if (!is_string($filename) || $filename === '') {
            $response = $this->dispatchApiRoute('GET', "/projects/{$project}/domains/{$domain}/log-files", $params);
            return $this->writeResponseBody($response, null);
        }

        $response = $this->dispatchApiRoute(
            'GET',
            "/projects/{$project}/domains/{$domain}/log-files/" . rawurlencode($filename),
            $params
        );

        return $this->writeResponseBody($response, is_string($out) && $out !== '' ? $out : null);
    }
}
