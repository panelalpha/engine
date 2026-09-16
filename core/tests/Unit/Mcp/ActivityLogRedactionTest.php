<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Tools\Api\ApiTool;
use App\Models\McpActivityLog;
use Tests\TestCase;

/**
 * mcp_activity_logs rows are readable through GET /api/mcp-activity-logs, and
 * fourteen tools take a credential as an argument. Nothing may store one.
 */
class ActivityLogRedactionTest extends TestCase
{
    public function test_it_redacts_credentials_at_the_top_level(): void
    {
        $redacted = McpActivityLog::redact([
            'username' => 'johndoe',
            'password' => 'hunter2',
            'git_token' => 'ghp_realtoken',
        ]);

        $this->assertSame('johndoe', $redacted['username']);
        $this->assertSame(McpActivityLog::REDACTED, $redacted['password']);
        $this->assertSame(McpActivityLog::REDACTED, $redacted['git_token']);
    }

    public function test_it_redacts_at_every_depth(): void
    {
        $redacted = McpActivityLog::redact([
            'env_vars' => ['APP_ENV' => 'production', 'secret' => 'shh'],
            'nested' => [['token' => 'abc']],
        ]);

        $this->assertSame('production', $redacted['env_vars']['APP_ENV']);
        $this->assertSame(McpActivityLog::REDACTED, $redacted['env_vars']['secret']);
        $this->assertSame(McpActivityLog::REDACTED, $redacted['nested'][0]['token']);
    }

    public function test_it_matches_keys_case_insensitively(): void
    {
        $redacted = McpActivityLog::redact(['Password' => 'hunter2', 'GIT_TOKEN' => 'x', 'Admin_Password' => 'p']);

        $this->assertSame(McpActivityLog::REDACTED, $redacted['Password']);
        $this->assertSame(McpActivityLog::REDACTED, $redacted['GIT_TOKEN']);
        $this->assertSame(McpActivityLog::REDACTED, $redacted['Admin_Password']);
    }

    public function test_it_leaves_ordinary_arguments_and_shape_alone(): void
    {
        $input = ['username' => 'johndoe', 'per_page' => 15, 'enabled' => true, 'list' => ['a', 'b']];

        $this->assertSame($input, McpActivityLog::redact($input));
    }

    /**
     * The guard is only worth anything if it covers the arguments the tools
     * actually declare, so take the names from the generated tools themselves
     * rather than from a list written alongside the guard.
     */
    public function test_every_credential_argument_a_generated_tool_declares_is_covered(): void
    {
        /** @var array<int, class-string<ApiTool>> $classes */
        $classes = require base_path('app/Mcp/Tools/Api/generated-tools.php');

        $uncovered = [];

        foreach ($classes as $class) {
            $tool = new $class();

            if (!$tool instanceof ApiTool) {
                continue;
            }

            $properties = (array)($tool->toArray()['inputSchema']['properties'] ?? []);

            foreach (array_keys($properties) as $name) {
                if (!preg_match('/(password|passwd|token|secret|private_key|api_key)$/i', (string)$name)) {
                    continue;
                }

                if (McpActivityLog::redact([$name => 'x'])[$name] !== McpActivityLog::REDACTED) {
                    $uncovered[] = $tool->name() . ' -> ' . $name;
                }
            }
        }

        $this->assertSame(
            [],
            $uncovered,
            "Tool arguments that look like credentials but are not in McpActivityLog::REDACT_SUFFIXES:\n"
                . implode("\n", $uncovered)
        );
    }
}
