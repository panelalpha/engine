<?php

namespace App\Jobs;

use App\Jobs\Concerns\AttachTask;
use App\Models\User;
use App\System;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateStaging implements ShouldQueue
{
    use AttachTask;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public string $destUsername)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $this->runTask(function (): void {
            $dest = User::findByUsernameOrFail($this->destUsername);
            $source = $dest->liveUser;
            if ($source === null) {
                throw new \RuntimeException("Staging '{$this->destUsername}' has no live parent.");
            }

            (new System())->projects()->copy($source->username, $dest->username);
        });
    }

    public function failed(\Throwable $e): void
    {
        // The task record is the only trace of this failure that outlives
        // the account deletion below -- mark it before anything else, unless
        // runTask() already did (isTerminal guards against clobbering that).
        $task = $this->task();
        if ($task !== null && !$task->isTerminal()) {
            $this->markFailed($e);
        }

        $dest = User::findByUsername($this->destUsername);
        if ($dest === null) {
            return;
        }

        try {
            $dest->markStagingFailed($e->getMessage());
        } catch (\Throwable $mark) {
            Log::error('Could not mark staging failed', ['dest' => $this->destUsername, 'exception' => $mark]);
        }

        $source = $dest->liveUser;
        if ($source !== null) {
            try {
                (new System())->project($source)->copyVolumesForClone()?->resumeAfterCopy();
            } catch (\Throwable $ignored) {
            }
        }

        try {
            $dest->project()->destroy();
        } catch (\Throwable $cleanup) {
            Log::error('CreateStaging cleanup failed', [
                'dest'      => $this->destUsername,
                'exception' => $cleanup,
            ]);
        }
    }
}
