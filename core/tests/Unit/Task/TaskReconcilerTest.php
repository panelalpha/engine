<?php

namespace Tests\Unit\Task;

use App\Lib\Deploy\DeployLog\DeployLogger;
use App\Lib\Task\TaskReconciler;
use App\Models\Task;

class TaskReconcilerTest extends SqliteTaskTestCase
{
    private static int $jobSeq = 0;

    private function runningTask(
        string $jobType = 'App\\Jobs\\DeployProject',
        string $queue = 'default',
    ): Task {
        $task = Task::start(jobType: $jobType, queue: $queue, username: 'shop');
        $task->markRunning('h-' . ++self::$jobSeq);

        return $task->refresh();
    }

    /**
     * @param array<string, mixed> $latest
     */
    private function log(string $status, ?string $error = null, ?int $startedAt = null): array
    {
        return [
            'id' => 'd-1',
            'status' => $status,
            'stage' => 'running',
            'pid' => null,
            'started_at' => $startedAt ?? time(),
            'finished_at' => null,
            'error' => $error,
        ];
    }

    /** The queue says the job is gone. */
    private function gone(): callable
    {
        return static fn (Task $t): bool => false;
    }

    /** The queue says a worker still owes this job an answer. */
    private function queued(): callable
    {
        return static fn (Task $t): bool => true;
    }

    /** The queue could not be read. */
    private function unknown(): callable
    {
        return static fn (Task $t): ?bool => null;
    }

    // ---- a terminal deploy log is a real verdict -------------------------

    public function test_a_deploy_log_that_finished_completes_the_row(): void
    {
        $task = $this->runningTask();

        $this->assertSame(
            Task::STATUS_COMPLETED,
            TaskReconciler::decide($task, $this->log(DeployLogger::STATUS_SUCCESS), $this->gone())
        );
    }

    public function test_a_partial_deploy_counts_as_completed(): void
    {
        $task = $this->runningTask();

        $this->assertSame(
            Task::STATUS_COMPLETED,
            TaskReconciler::decide($task, $this->log(DeployLogger::STATUS_PARTIAL), $this->gone())
        );
    }

    public function test_a_deploy_log_that_failed_fails_the_row(): void
    {
        $task = $this->runningTask();

        $this->assertSame(
            Task::STATUS_FAILED,
            TaskReconciler::decide($task, $this->log(DeployLogger::STATUS_FAILED, 'boom'), $this->gone())
        );
    }

    public function test_a_terminal_log_wins_even_while_the_job_is_still_reserved(): void
    {
        // The work is over; the row merely never heard. A log that says so is
        // adopted whether or not a worker still holds the reservation.
        $task = $this->runningTask();

        $this->assertSame(
            Task::STATUS_COMPLETED,
            TaskReconciler::decide($task, $this->log(DeployLogger::STATUS_SUCCESS), $this->queued())
        );
    }

    // ---- the queue decides the rest --------------------------------------

    public function test_a_job_the_queue_no_longer_holds_is_cancelled_with_no_log(): void
    {
        // The reboot case: the work never wrote a log, and no worker took it.
        $task = $this->runningTask();

        $this->assertSame(Task::STATUS_CANCELLED, TaskReconciler::decide($task, null, $this->gone()));
    }

    public function test_a_job_the_queue_still_holds_is_left_alone(): void
    {
        // This is the regression that mattered: a deploy midway through its
        // rollback has already deleted its deploy log, and an earlier version
        // of this class read that as death and cancelled it.
        $task = $this->runningTask();

        $this->assertNull(TaskReconciler::decide($task, null, $this->queued()));
    }

    public function test_a_job_the_queue_still_holds_is_left_alone_mid_deploy(): void
    {
        $task = $this->runningTask();

        $this->assertNull(
            TaskReconciler::decide($task, $this->log(DeployLogger::STATUS_RUNNING), $this->queued())
        );
    }

    public function test_an_unreadable_queue_is_not_evidence_of_death(): void
    {
        $task = $this->runningTask();

        $this->assertNull(TaskReconciler::decide($task, null, $this->unknown()));
        $this->assertNull(
            TaskReconciler::decide($task, $this->log(DeployLogger::STATUS_RUNNING), $this->unknown())
        );
    }

    public function test_a_job_that_left_the_queue_with_a_running_log_is_cancelled(): void
    {
        // Still mid-deploy by its own log, but nothing in the queue can ever
        // finish it, so it would otherwise read `running` for good.
        $task = $this->runningTask();

        $this->assertSame(
            Task::STATUS_CANCELLED,
            TaskReconciler::decide($task, $this->log(DeployLogger::STATUS_RUNNING), $this->gone())
        );
    }

    // ---- the sweep -------------------------------------------------------

    public function test_reconcile_retires_only_the_orphan_and_is_dry_run_safe(): void
    {
        $orphan = $this->runningTask();

        $dry = TaskReconciler::reconcile(true, 0, $this->gone());
        $this->assertSame([$orphan->id], $dry);
        $this->assertSame(Task::STATUS_RUNNING, $orphan->refresh()->status);

        $retired = TaskReconciler::reconcile(false, 0, $this->gone());
        $this->assertSame([$orphan->id], $retired);
        $this->assertSame(Task::STATUS_CANCELLED, $orphan->refresh()->status);
        $this->assertNotNull($orphan->cancelled_at);
        $this->assertStringContainsString('stopped without finishing', $orphan->details['reconciled']);
    }

    public function test_reconcile_leaves_queued_jobs_alone(): void
    {
        $live = $this->runningTask();

        $this->assertSame([], TaskReconciler::reconcile(false, 0, $this->queued()));
        $this->assertSame(Task::STATUS_RUNNING, $live->refresh()->status);
    }

    public function test_a_recent_task_is_not_touched(): void
    {
        // The grace period keeps the sweep from racing a deploy that has
        // marked itself running but whose job has not been pushed yet.
        $orphan = $this->runningTask();

        $this->assertSame([], TaskReconciler::reconcile(false, 3600, $this->gone()));
        $this->assertSame(Task::STATUS_RUNNING, $orphan->refresh()->status);
    }

    public function test_the_sweep_leaves_terminal_tasks_alone(): void
    {
        $done = $this->runningTask();
        $done->markCompleted();

        $this->assertSame([], TaskReconciler::reconcile(false, 0, $this->gone()));
        $this->assertSame(Task::STATUS_COMPLETED, $done->refresh()->status);
    }

    public function test_a_non_deploy_job_is_reconciled_by_the_queue_too(): void
    {
        // It has no deploy log, but the queue still knows whether a worker is
        // going to finish it -- which is all this needs.
        $backup = $this->runningTask('App\\Jobs\\CreateBackup');

        $this->assertNull(TaskReconciler::decide($backup, null, $this->queued()));
        $this->assertSame(Task::STATUS_CANCELLED, TaskReconciler::decide($backup, null, $this->gone()));
    }

    // ---- the real queue read ------------------------------------------
    //
    // Every test above injects a queue verdict. These exercise the read that
    // produces one, because the read had a bug no injected verdict could
    // expose: it asked Redis for a member equal to the bare uuid, and Laravel
    // stores the job's whole serialised payload as the member.

    /**
     * A queue member as Laravel writes it: the payload *is* the member, with
     * the uuid as a field inside it.
     */
    private function member(string $uuid): string
    {
        return json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\\Jobs\\DeployProject',
            'data' => ['uuid' => $uuid, 'commandName' => 'App\\Jobs\\DeployProject'],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * A Redis connection that answers from fixed lists instead of a server.
     *
     * @param array<int, string> $reserved raw ZSET members
     * @param array<int, string> $pending  raw list members
     */
    private function redis(array $reserved = [], array $pending = [], bool $missingKey = false): object
    {
        return new class($reserved, $pending, $missingKey)
        {
            /** @param array<int, string> $reserved */
            public function __construct(
                private array $reserved,
                private array $pending,
                private bool $missingKey,
            ) {}

            /** @return array<int, string>|false */
            public function zrange(string $key, int $start, int $end): array|false
            {
                return $this->missingKey ? false : $this->reserved;
            }

            /** @return array<int, string>|false */
            public function lrange(string $key, int $start, int $end): array|false
            {
                return $this->missingKey ? false : $this->pending;
            }
        };
    }

    public function test_a_running_job_reserved_by_a_worker_is_not_retired(): void
    {
        $task = $this->runningTask();
        $uuid = (string) $task->job_id;

        // The member is the payload, not the uuid -- the shape that made the
        // first version of this class retire nothing.
        $check = TaskReconciler::queueCheck($this->redis(reserved: [$this->member($uuid)]));

        $this->assertTrue($check($task));
        $this->assertNull(TaskReconciler::decide($task, null, $check));
    }

    public function test_a_running_job_still_pending_is_not_retired(): void
    {
        $task = $this->runningTask();
        $check = TaskReconciler::queueCheck($this->redis(pending: [$this->member((string) $task->job_id)]));

        $this->assertTrue($check($task));
        $this->assertNull(TaskReconciler::decide($task, null, $check));
    }

    public function test_a_job_absent_from_both_queues_is_gone(): void
    {
        // The case that must work, and did not: the deploy died with its
        // worker, nothing finished it, and the row has to be retired.
        $task = $this->runningTask();
        $check = TaskReconciler::queueCheck($this->redis(reserved: [$this->member('someone-elses-job')]));

        $this->assertFalse($check($task));
        $this->assertSame(Task::STATUS_CANCELLED, TaskReconciler::decide($task, null, $check));
    }

    public function test_empty_queues_are_gone_not_unknown(): void
    {
        // PhpRedis answers `false` rather than `[]` for a missing key. Read as
        // "unknown" that would leave every orphan running; read as empty it is
        // the plainest evidence there is that the job is over.
        $task = $this->runningTask();
        $check = TaskReconciler::queueCheck($this->redis(missingKey: true));

        $this->assertFalse($check($task));
        $this->assertSame(Task::STATUS_CANCELLED, TaskReconciler::decide($task, null, $check));
    }

    public function test_a_nested_uuid_is_still_the_same_job(): void
    {
        $task = $this->runningTask();
        $member = json_encode([
            'displayName' => 'App\\Jobs\\DeployProject',
            'data' => ['uuid' => (string) $task->job_id],
        ], JSON_THROW_ON_ERROR);

        $this->assertTrue(TaskReconciler::membersCarryJob([$member], (string) $task->job_id));
    }

    public function test_a_garbage_member_is_not_a_match(): void
    {
        $this->assertFalse(TaskReconciler::membersCarryJob(['not json'], 'job-1'));
        $this->assertFalse(TaskReconciler::membersCarryJob(['[]'], 'job-1'));
        $this->assertFalse(TaskReconciler::membersCarryJob([], 'job-1'));
    }

    public function test_an_unreadable_queue_leaves_the_task_alone(): void
    {
        $task = $this->runningTask();

        $check = TaskReconciler::queueCheck(new class
        {
            public function zrange(string $key, int $start, int $end): array
            {
                throw new \RuntimeException('connection refused');
            }

            /** @return array<int, string> */
            public function lrange(string $key, int $start, int $end): array
            {
                return [];
            }
        });

        $this->assertNull($check($task));
        $this->assertNull(TaskReconciler::decide($task, null, $check));
    }
}
