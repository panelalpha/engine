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

class RestoreBackup implements ShouldQueue
{
    use AttachTask;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    /**
     * @param array<string, mixed> $only
     * @param array<string, mixed> $exclude
     */
    public function __construct(
        public int $backupId,
        public array $only = [],
        public array $exclude = [],
    ) {
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
                $record->setRestoreStatus('failed');
                $record->error = 'Backup user not found';
                $record->save();
                throw new RuntimeException('Backup user not found');
            }

            $user->project()->backup($record)->restore($this->only, $this->exclude);

            $record->refresh();
            if ($record->restoreStatus() === 'failed') {
                throw new RuntimeException($record->error ?? 'Restore failed');
            }
        });
    }

    public function failed(?Throwable $e): void
    {
        $record = BackupRecord::query()->find($this->backupId);
        if ($record !== null) {
            $status = $record->restoreStatus();
            if (!in_array($status, ['completed', 'failed'], true)) {
                $record->setRestoreStatus('failed');
                $record->error = $e?->getMessage() ?? 'Restore job failed';
                $record->save();
            }
        }

        $task = $this->task();
        if ($task !== null && !$task->isTerminal()) {
            $this->markFailed($e ?? new RuntimeException('Restore job failed'));
        }
    }
}
