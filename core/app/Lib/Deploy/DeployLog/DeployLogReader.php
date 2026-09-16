<?php

namespace App\Lib\Deploy\DeployLog;

/**
 * Parsed views over one deploy's JSON-lines log.
 *
 * The panel pages forward from an offset it remembers; telemetry wants the
 * other end and does not know the total; the build breakdown needs the whole
 * thing, because a `#N [x/y] <cmd>` and its `#N DONE <s>` can be thousands of
 * lines apart and a window that split the two would report the step as
 * costing nothing.
 */
final class DeployLogReader
{
    public const MAX_READ_LINES = 2000;

    public function __construct(private readonly string $path)
    {
    }

    /**
     * Every line, timestamped, in order.
     *
     * @return list<array{ts: int, level: string, msg: string}>
     */
    public function entries(): array
    {
        $entries = [];
        foreach ($this->decodedLines() as $decoded) {
            if (isset($decoded['msg'])) {
                $entries[] = [
                    'ts' => (int) ($decoded['ts'] ?? 0),
                    'level' => (string) ($decoded['level'] ?? DeployLogger::LEVEL_DIM),
                    'msg' => (string) $decoded['msg'],
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array{lines: list<array{ts: int, stage: ?string, level: string, msg: string}>, next_offset: int}
     */
    public function page(int $offset = 0, int $limit = self::MAX_READ_LINES): array
    {
        $raw = $this->rawLines();

        return [
            'lines' => $this->parse(array_slice($raw, max(0, $offset), $limit)),
            'next_offset' => count($raw),
        ];
    }

    /**
     * @return list<array{ts: int, stage: ?string, level: string, msg: string}>
     */
    public function tail(int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        return $this->parse(array_slice($this->rawLines(), -$limit));
    }

    /**
     * @param list<string> $raw
     * @return list<array{ts: int, stage: ?string, level: string, msg: string}>
     */
    private function parse(array $raw): array
    {
        $lines = [];
        foreach ($raw as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $lines[] = [
                    'ts' => (int) ($decoded['ts'] ?? 0),
                    'stage' => $decoded['stage'] ?? null,
                    'level' => (string) ($decoded['level'] ?? DeployLogger::LEVEL_DIM),
                    'msg' => (string) ($decoded['msg'] ?? ''),
                ];
            }
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodedLines(): array
    {
        $decoded = [];
        foreach ($this->rawLines() as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $decoded[] = $entry;
            }
        }

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function rawLines(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        return file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    }
}
