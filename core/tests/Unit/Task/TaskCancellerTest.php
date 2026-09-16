<?php

namespace Tests\Unit\Task;

use App\Lib\Task\ProcessTreeKiller;
use App\Lib\Task\TaskCanceller;
use App\Models\Task;
use Mockery;

class TaskCancellerTest extends SqliteTaskTestCase
{
    public function test_cancel_on_terminal_task_is_rejected(): void
    {
        $task = Task::start(jobType: 'App\\Jobs\\RebuildJob', queue: 'default');
        $task->markCompleted();
        $killer = Mockery::mock(ProcessTreeKiller::class);
        $killer->shouldNotReceive('kill');

        $result = (new TaskCanceller($killer))->cancel($task);

        $this->assertFalse($result['cancelled']);
        $this->assertSame('Task cannot be cancelled', $result['message']);
        $this->assertSame(Task::STATUS_COMPLETED, $task->refresh()->status);
    }

    public function test_cancel_on_queued_task_marks_cancelled_without_kill(): void
    {
        $task = Task::start(jobType: 'App\\Jobs\\RebuildJob', queue: 'default');
        $killer = Mockery::mock(ProcessTreeKiller::class);
        $killer->shouldNotReceive('kill');

        $result = (new TaskCanceller($killer))->cancel($task);

        $this->assertTrue($result['cancelled']);
        $this->assertSame(Task::STATUS_CANCELLED, $task->refresh()->status);
        $this->assertNotNull($task->cancelled_at);
    }

    public function test_cancel_does_not_kill_when_pid_is_no_longer_the_same_process(): void
    {
        $task = Task::start(jobType: 'App\\Jobs\\RebuildJob', queue: 'default');
        $task->markRunning('h-1');
        $task->setPid(99999, '1');
        $killer = Mockery::mock(ProcessTreeKiller::class);
        $killer->shouldNotReceive('kill');

        $result = (new TaskCanceller($killer))->cancel($task);

        $this->assertTrue($result['cancelled']);
    }

    public function test_cancel_kills_when_process_identity_matches(): void
    {
        $task = Task::start(jobType: 'App\\Jobs\\RebuildJob', queue: 'default');
        $task->markRunning('h-1');
        $task->setPid(4242, '99');
        $killer = Mockery::mock(ProcessTreeKiller::class);
        $killer->shouldReceive('kill')->once()->with(4242);

        $isStillRunning = function ($pid, $startTime): bool {
            return $pid === 4242 && $startTime === '99';
        };

        $result = (new TaskCanceller($killer, $isStillRunning))->cancel($task);

        $this->assertTrue($result['cancelled']);
        $this->assertSame(Task::STATUS_CANCELLED, $task->refresh()->status);
    }
}
