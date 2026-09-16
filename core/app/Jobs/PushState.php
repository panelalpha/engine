<?php

namespace App\Jobs;

use App\Models\User;
use App\System;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushState implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public string $fromUsername, public string $toUsername)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        (new System())->projects()->pushToApp($this->fromUsername, $this->toUsername);
    }

    public function failed(\Throwable $e): void
    {
        $from = User::findByUsername($this->fromUsername);
        $to = User::findByUsername($this->toUsername);
        $system = new System();

        if ($to !== null) {
            $to->mergeAsyncStatus(['push' => 'failed']);
            $to->setDetails(['error' => $e->getMessage()]);
            $to->save();

            try {
                $incoming = rtrim($system->project($to)->homeDirPath(), '/') . '/.incoming';
                $system->runProcess(['sudo', 'rm', '-rf', $incoming]);
            } catch (\Throwable $cleanup) {
                Log::warning('PushState failed cleanup could not remove .incoming', [
                    'target'    => $this->toUsername,
                    'exception' => $cleanup->getMessage(),
                ]);
            }
        }

        if ($from !== null) {
            try {
                $system->project($from)->copyVolumesForClone()?->resumeAfterCopy();
            } catch (\Throwable $ignored) {
            }
        }

        if ($to !== null) {
            try {
                $system->project($to)->copyVolumesForClone()?->resumeAfterCopy();
            } catch (\Throwable $ignored) {
            }
        }
    }
}
