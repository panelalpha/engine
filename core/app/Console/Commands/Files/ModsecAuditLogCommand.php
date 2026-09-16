<?php

namespace App\Console\Commands\Files;

use App\Console\Commands\Concerns\DispatchesApiRoute;
use Illuminate\Console\Command;

class ModsecAuditLogCommand extends Command
{
    use DispatchesApiRoute;

    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['modsec:audit-log'];

    protected $signature = 'modsec:log:show
                            {filename? : Audit log file to fetch; omit to list what is available}
                            {--tail : Read the tail of the file instead of downloading the whole of it}
                            {--out= : Local file to write (default: stdout)}';

    protected $description = 'List or download a ModSecurity audit log file (GET /modsec/audit-log/files)';

    public function handle(): int
    {
        $filename = $this->argument('filename');
        $out = $this->option('out');

        if (!is_string($filename) || $filename === '') {
            return $this->writeResponseBody($this->dispatchApiRoute('GET', '/modsec/audit-log/files'), null);
        }

        $uri = '/modsec/audit-log/files/' . rawurlencode($filename);
        if ($this->option('tail')) {
            // The tail endpoint answers with JSON, so it never needed a command
            // of its own - it is here so one command covers the whole file.
            return $this->writeResponseBody($this->dispatchApiRoute('GET', $uri . '/tail'), null);
        }

        return $this->writeResponseBody(
            $this->dispatchApiRoute('GET', $uri),
            is_string($out) && $out !== '' ? $out : null
        );
    }
}
