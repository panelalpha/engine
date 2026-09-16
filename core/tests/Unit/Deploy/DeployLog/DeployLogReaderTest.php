<?php

namespace Tests\Unit\Deploy\DeployLog;

use App\Lib\Deploy\DeployLog\DeployLogReader;
use PHPUnit\Framework\TestCase;

/**
 * Parsed views over one deploy's JSON-lines log.
 *
 * Three callers want three different windows: the panel pages forward from an
 * offset it remembers, telemetry wants the other end and does not know the
 * total, and the build breakdown needs the whole file - a `#N [x/y] <cmd>`
 * and its `#N DONE <s>` can be thousands of lines apart, and a window that
 * split the two would report the step as costing nothing.
 */
class DeployLogReaderTest extends TestCase
{
    private string $path = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir() . '/pa-log-' . bin2hex(random_bytes(8)) . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    private function write(string ...$lines): DeployLogReader
    {
        file_put_contents($this->path, implode("\n", $lines) . "\n");

        return new DeployLogReader($this->path);
    }

    private function entry(int $ts, string $msg, string $stage = 'building', string $level = 'info'): string
    {
        return (string) json_encode(['ts' => $ts, 'stage' => $stage, 'level' => $level, 'msg' => $msg]);
    }

    public function test_every_entry_is_returned_in_order(): void
    {
        $reader = $this->write($this->entry(100, 'first'), $this->entry(200, 'second'));

        $this->assertSame([
            ['ts' => 100, 'level' => 'info', 'msg' => 'first'],
            ['ts' => 200, 'level' => 'info', 'msg' => 'second'],
        ], $reader->entries());
    }

    public function test_a_page_starts_where_the_caller_left_off(): void
    {
        $reader = $this->write(
            $this->entry(100, 'a'),
            $this->entry(200, 'b'),
            $this->entry(300, 'c')
        );

        $page = $reader->page(1, 10);

        $this->assertSame(['b', 'c'], array_column($page['lines'], 'msg'));
    }

    public function test_a_page_reports_where_to_resume_from(): void
    {
        // The total, not the end of this page: a poll that asks again with
        // this offset gets only what was appended since.
        $reader = $this->write($this->entry(100, 'a'), $this->entry(200, 'b'), $this->entry(300, 'c'));

        $this->assertSame(3, $reader->page(0, 1)['next_offset']);
        $this->assertSame(3, $reader->page(2, 10)['next_offset']);
    }

    public function test_a_page_past_the_end_is_empty_but_still_says_where_to_resume(): void
    {
        // What every poll after the deploy finishes looks like.
        $reader = $this->write($this->entry(100, 'a'));
        $page = $reader->page(5, 10);

        $this->assertSame([], $page['lines']);
        $this->assertSame(1, $page['next_offset']);
    }

    public function test_a_negative_offset_is_read_from_the_start(): void
    {
        $reader = $this->write($this->entry(100, 'a'), $this->entry(200, 'b'));

        $this->assertSame(['a', 'b'], array_column($reader->page(-5, 10)['lines'], 'msg'));
    }

    public function test_a_page_carries_the_stage_each_line_belonged_to(): void
    {
        $reader = $this->write(
            $this->entry(100, 'cloning', 'cloning'),
            $this->entry(200, 'building', 'building')
        );

        $this->assertSame(['cloning', 'building'], array_column($reader->page()['lines'], 'stage'));
    }

    public function test_the_tail_returns_the_most_recent_lines(): void
    {
        $reader = $this->write($this->entry(100, 'a'), $this->entry(200, 'b'), $this->entry(300, 'c'));

        $this->assertSame(['b', 'c'], array_column($reader->tail(2), 'msg'));
    }

    public function test_a_tail_longer_than_the_log_returns_all_of_it(): void
    {
        $reader = $this->write($this->entry(100, 'a'));

        $this->assertCount(1, $reader->tail(50));
    }

    public function test_a_tail_of_nothing_is_nothing(): void
    {
        $reader = $this->write($this->entry(100, 'a'));

        $this->assertSame([], $reader->tail(0));
        $this->assertSame([], $reader->tail(-1));
    }

    public function test_a_corrupt_line_is_skipped_rather_than_fatal(): void
    {
        // A deploy killed mid-write leaves a half-line. The rest of the log
        // is still the only record of what happened.
        $reader = $this->write($this->entry(100, 'a'), '{"ts": 200, "msg"', $this->entry(300, 'c'));

        $this->assertSame(['a', 'c'], array_column($reader->entries(), 'msg'));
        $this->assertSame(['a', 'c'], array_column($reader->page()['lines'], 'msg'));
    }

    public function test_an_entry_with_no_message_is_not_an_entry(): void
    {
        $reader = $this->write('{"ts": 100, "level": "info"}', $this->entry(200, 'b'));

        $this->assertSame(['b'], array_column($reader->entries(), 'msg'));
    }

    public function test_missing_fields_fall_back_rather_than_throwing(): void
    {
        $reader = $this->write('{"msg": "no timestamp"}');
        $line = $reader->page()['lines'][0];

        $this->assertSame(0, $line['ts']);
        $this->assertNull($line['stage']);
        $this->assertNotSame('', $line['level']);
    }

    public function test_a_log_that_is_not_there_reads_as_empty(): void
    {
        $reader = new DeployLogReader($this->path);

        $this->assertSame([], $reader->entries());
        $this->assertSame([], $reader->tail(10));
        $this->assertSame(['lines' => [], 'next_offset' => 0], $reader->page());
    }
}
