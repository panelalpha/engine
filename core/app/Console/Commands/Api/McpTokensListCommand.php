<?php

namespace App\Console\Commands\Api;

use App\Models\Admin;
use Illuminate\Console\Command;

class McpTokensListCommand extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['mcp-tokens:list'];

    protected $signature = 'mcp:token:list';

    protected $description = 'List all MCP tokens for the root admin';

    public function handle(): int
    {
        $root   = Admin::rootAccount();
        $tokens = $root->tokens()
            ->where(function ($q) {
                $q->whereJsonContains('abilities', 'mcp')
                  ->orWhereNull('abilities');
            })
            ->get(['id', 'name', 'last_used_at', 'revoked_at', 'created_at'])
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'last_used_at' => $t->last_used_at ?? 'never',
                'revoked_at'   => $t->revoked_at ?? '-',
                'created_at'   => $t->created_at,
            ])
            ->toArray();

        $this->table(
            ['ID', 'Name', 'Last Used', 'Revoked At', 'Created'],
            $tokens
        );
        return 0;
    }
}
