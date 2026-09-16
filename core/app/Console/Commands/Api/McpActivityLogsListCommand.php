<?php

namespace App\Console\Commands\Api;

use App\Models\McpActivityLog;
use Illuminate\Console\Command;

class McpActivityLogsListCommand extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['mcp-activity-logs:list'];

    protected $signature = 'mcp:log:list {--limit=20}';

    protected $description = 'List recent MCP activity log entries';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $rows = McpActivityLog::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'token_name', 'tool_name', 'status', 'error_message', 'created_at'])
            ->map(fn($r) => [
                'id'            => $r->id,
                'token'         => $r->token_name,
                'tool'          => $r->tool_name,
                'status'        => $r->status,
                'error'         => $r->error_message ? substr($r->error_message, 0, 60) : '-',
                'created_at'    => $r->created_at,
            ])
            ->toArray();

        $this->table(
            ['ID', 'Token', 'Tool', 'Status', 'Error', 'Time'],
            $rows
        );
        return 0;
    }
}
