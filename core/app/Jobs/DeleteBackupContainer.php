<?php

namespace App\Jobs;

use App\Jobs\Concerns\AttachTask;
use App\Models\Backup as BackupRecord;
use App\Models\BackupContainer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class DeleteBackupContainer implements ShouldQueue
{
    use AttachTask;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $containerId)
    {
        $this->onQueue('default');
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        $this->runTask(function (): void {
            $container = BackupContainer::query()->find($this->containerId);
            if ($container === null) {
                return;
            }

            $backups = $container->backups()->with('container')->get();
            foreach ($backups as $backup) {
                $user = User::query()->find($backup->user_id);
                if ($user === null) {
                    throw new RuntimeException("Backup {$backup->id} has no owning user");
                }

                $backupId = $backup->id;
                $user->project()->backup($backup)->delete();

                if (BackupRecord::query()->find($backupId) !== null) {
                    $leftover = BackupRecord::query()->find($backupId);
                    throw new RuntimeException(
                        $leftover?->error ?? "Failed to delete backup {$backupId}",
                    );
                }
            }

            $container->refresh();
            if ($container->backups()->exists()) {
                throw new RuntimeException('Cannot delete backup container while backups exist');
            }

            $container->delete();
        });
    }

    public function failed(?Throwable $e): void
    {
        $task = $this->task();
        if ($task !== null && !$task->isTerminal()) {
            $this->markFailed($e ?? new RuntimeException('Delete backup container job failed'));
        }
    }
}
