<?php

namespace App\Console\Commands\Task;

use App\Lib\Task\TaskReconciler;
use Illuminate\Console\Command;

class ReconcileTasksCommand extends Command
{
    protected $signature = 'task:reconcile
                            {--dry-run : Report what would be retired without changing it}
                            {--older-than=120 : Ignore tasks started this many seconds ago}';

    protected $description = 'Retire running tasks whose job has left the queue without finishing (host reboot, OOM kill)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $olderThan = max(0, (int) $this->option('older-than'));

        $retired = TaskReconciler::reconcile($dryRun, $olderThan);

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info($prefix . 'Retired ' . count($retired) . ' orphaned task(s).'
            . ($retired === [] ? '' : ' ids: ' . implode(', ', $retired)));

        return 0;
    }
}
