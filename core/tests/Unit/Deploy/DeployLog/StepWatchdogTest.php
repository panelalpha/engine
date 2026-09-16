<?php

namespace Tests\Unit\Deploy\DeployLog;

use App\Exceptions\BuildStalledException;
use App\System;
use App\Lib\Deploy\DeployLog\DeployFailureExplainer;
use App\Lib\Deploy\DeployLog\StepWatchdog;
use PHPUnit\Framework\TestCase;

class StepWatchdogTest extends TestCase
{
    private string $pidFile;

    protected function setUp(): void
    {
        $this->pidFile = tempnam(sys_get_temp_dir(), 'watchdog-');
    }

    protected function tearDown(): void
    {
        foreach ($this->pids() as $pid) {
            @posix_kill($pid, 9);
        }
        @unlink($this->pidFile);
    }

    public function test_a_silent_step_is_killed_after_the_limit(): void
    {
        $started = microtime(true);
        try {
            $this->runWatched(['sh', '-c', 'echo "Chromium download 0% of 153.1 Mb"; sleep 30'], 1);
            $this->fail('A silent step was not stopped');
        } catch (BuildStalledException $e) {
            $this->assertStringStartsWith(StepWatchdog::MARKER, $e->getMessage());
            $this->assertStringContainsString('printed nothing for 1s', $e->getMessage());
            $this->assertStringContainsString('Last output: "Chromium download 0% of 153.1 Mb"', $e->getMessage());
            $this->assertSame('build-stalled', DeployFailureExplainer::match($e->getMessage())['rule'] ?? null);
        }
        $this->assertLessThan(8, microtime(true) - $started);
    }

    public function test_a_step_that_keeps_printing_is_not_killed(): void
    {
        $output = '';
        $process = $this->runWatched(
            ['sh', '-c', 'for i in 1 2 3 4 5 6; do echo tick $i; sleep 0.4; done'],
            1,
            function (string $type, string $data) use (&$output): void {
                $output .= $data;
            }
        );

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('tick 6', $output);
    }

    public function test_the_whole_process_tree_is_gone_after_the_kill(): void
    {
        $file = escapeshellarg($this->pidFile);
        try {
            $this->runWatched(['sh', '-c', "sleep 30 & echo \$! >> {$file}; setsid sleep 30 & echo \$! >> {$file}; wait"], 1);
            $this->fail('A silent step was not stopped');
        } catch (BuildStalledException) {
        }

        $pids = $this->pids();
        $this->assertCount(2, $pids);
        $deadline = microtime(true) + 3;
        while ($this->alive($pids) !== [] && microtime(true) < $deadline) {
            usleep(100_000);
        }
        $this->assertSame([], $this->alive($pids));
    }

    public function test_the_overall_timeout_still_applies(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessTimedOutException::class);
        (new System())->runProcessWithCallbacks(
            ['sh', '-c', 'while true; do echo x; sleep 0.2; done'],
            [],
            1,
            null,
            null,
            new StepWatchdog(5, 'loop')
        );
    }

    public function test_the_explainer_names_the_step_the_silence_and_the_last_line(): void
    {
        $message = (new StepWatchdog(900, 'docker compose up -d --build'))->message(900);
        $match = DeployFailureExplainer::match("#12 5.1 noise\n" . $message . "\nno space left on device");

        $this->assertSame('build-stalled', $match['rule'] ?? null);
        $this->assertStringContainsString('"docker compose up -d --build"', $match['message']);
        $this->assertStringContainsString('15 minutes', $match['message']);
        $this->assertStringContainsString('Last output: (none)', $match['message']);
    }

    /**
     * @param list<string> $cmd
     */
    private function runWatched(array $cmd, int $idle, ?callable $onOutput = null): \Symfony\Component\Process\Process
    {
        return (new System())->runProcessWithCallbacks(
            $cmd,
            [],
            60,
            null,
            $onOutput,
            new StepWatchdog($idle, implode(' ', $cmd))
        );
    }

    /** @return list<int> */
    private function pids(): array
    {
        $lines = @file($this->pidFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_map('intval', $lines);
    }

    /**
     * @param list<int> $pids
     * @return list<int>
     */
    private function alive(array $pids): array
    {
        return array_values(array_filter($pids, static function (int $pid): bool {
            $stat = @file_get_contents("/proc/{$pid}/stat");

            return is_string($stat) && !str_contains(substr($stat, (int) strrpos($stat, ')')), ') Z ');
        }));
    }
}
