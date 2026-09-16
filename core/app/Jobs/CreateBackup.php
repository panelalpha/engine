<?php

namespace App\Jobs;

use App\Jobs\Concerns\AttachTask;
use App\Models\Backup as BackupRecord;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class CreateBackup implements ShouldQueue
{
    use AttachTask;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $backupId)
    {
        $this->onQueue('default');
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        $this->runTask(function (): void {
            $record = BackupRecord::query()->find($this->backupId);
            if ($record === null) {
                return;
            }

            $user = User::query()->find($record->user_id);
            if ($user === null) {
                $record->setBackupStatus('failed');
                $record->error = 'Backup user not found';
                $record->save();
                throw new RuntimeException('Backup user not found');
            }

            $user->project()->backup($record)->run();

            $record->refresh();
            if ($record->backupStatus() === 'failed') {
                throw new RuntimeException($record->error ?? 'Backup failed');
            }
        });
    }

    public function failed(?Throwable $e): void
    {
        $record = BackupRecord::query()->find($this->backupId);
        if ($record !== null) {
            $status = $record->backupStatus();
            if (!in_array($status, ['completed', 'failed'], true)) {
                $record->setBackupStatus('failed');
                $record->error = $e?->getMessage() ?? 'Backup job failed';
                $record->save();
            }
        }

        $task = $this->task();
        if ($task !== null && !$task->isTerminal()) {
            $this->markFailed($e ?? new RuntimeException('Backup job failed'));
        }
    }
}
