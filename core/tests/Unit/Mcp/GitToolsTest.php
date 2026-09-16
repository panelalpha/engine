<?php

namespace Tests\Unit\Mcp;

use App\Console\Commands\Mcp\GenerateApiToolsCommand;
use App\Mcp\Servers\EngineServer;
use App\Mcp\ToolPolicy;
use App\Mcp\Tools\Api\ApiTool;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use ReflectionClass;
use Tests\TestCase;

class GitToolsTest extends TestCase
{
    /** @var array<string, class-string<ApiTool>> */
    private const GIT_TOOLS = [
        'git_status' => \App\Mcp\Tools\Api\Git\GitStatusTool::class,
        'git_branches' => \App\Mcp\Tools\Api\Git\GitBranchesTool::class,
        'git_commits' => \App\Mcp\Tools\Api\Git\GitCommitsTool::class,
        'git_connect' => \App\Mcp\Tools\Api\Git\GitConnectTool::class,
        'git_disconnect' => \App\Mcp\Tools\Api\Git\GitDisconnectTool::class,
        'git_change_branch' => \App\Mcp\Tools\Api\Git\GitChangeBranchTool::class,
        'git_update_credentials' => \App\Mcp\Tools\Api\Git\GitUpdateCredentialsTool::class,
        'git_pull' => \App\Mcp\Tools\Api\Git\GitPullTool::class,
        'git_push' => \App\Mcp\Tools\Api\Git\GitPushTool::class,
        'git_revert' => \App\Mcp\Tools\Api\Git\GitRevertTool::class,
    ];

    /** Tokens every Git description must contain (path default). */
    private const PATH_LOCK = ['path', 'public_html'];

    /**
     * Extra lock tokens per tool. `public_html` / `reset --hard` / `Pull first`
     * are matched case-sensitively; everything else case-insensitively.
     *
     * @var array<string, list<string>>
     */
    private const LOCKS = [
        'git_status' => ['fetch', 'managed_by', 'deploy', 'site_git', 'project_rebuild'],
        'git_branches' => [],
        'git_commits' => ['branch', 'limit'],
        'git_connect' => ['repo_url', 'branch', 'token', 'repair', 'deploy'],
        'git_disconnect' => ['deploy', 'origin'],
        'git_change_branch' => ['branch', 'dirty', 'deploy', 'project_rebuild'],
        'git_update_credentials' => ['token', 'omit'],
        'git_pull' => ['ff', 'force', 'push_first', 'dirty', 'project_rebuild', 'reset --hard'],
        'git_push' => ['commit', 'Pull first', 'deploy'],
        'git_revert' => ['reset --hard', 'clean', 'HEAD'],
    ];

    /** @return array<string, string> */
    private function map(): array
    {
        return require base_path('app/Mcp/tool-names.php');
    }

    public function test_the_map_names_the_ten_git_operations(): void
    {
        $expected = [
            'GET /projects/{username}/git/status' => 'git_status',
            'GET /projects/{username}/git/branches' => 'git_branches',
            'GET /projects/{username}/git/commits' => 'git_commits',
            'POST /projects/{username}/git/connect' => 'git_connect',
            'POST /projects/{username}/git/disconnect' => 'git_disconnect',
            'PUT /projects/{username}/git/change-branch' => 'git_change_branch',
            'PUT /projects/{username}/git/update-credentials' => 'git_update_credentials',
            'POST /projects/{username}/git/pull' => 'git_pull',
            'POST /projects/{username}/git/push' => 'git_push',
            'POST /projects/{username}/git/revert' => 'git_revert',
        ];

        $map = $this->map();

        foreach ($expected as $operation => $name) {
            $this->assertArrayHasKey($operation, $map, "Add {$operation} to app/Mcp/tool-names.php");
            $this->assertSame($name, $map[$operation], "{$operation} must be named {$name}");
        }
    }

    public function test_server_instructions_tell_the_agent_how_to_use_git(): void
    {
        $attrs = (new ReflectionClass(EngineServer::class))->getAttributes(Instructions::class);
        $this->assertNotEmpty($attrs, 'EngineServer has no #[Instructions]');
        $text = (string) $attrs[0]->getArguments()[0];

        foreach (['git_status', 'managed_by', 'deploy', 'project_rebuild', 'git_push'] as $token) {
            $this->assertStringContainsString(
                $token,
                $text,
                "EngineServer #[Instructions] must mention {$token}"
            );
        }
    }

    public function test_generated_git_tools_exist_and_are_registered(): void
    {
        foreach (self::GIT_TOOLS as $name => $class) {
            $this->assertTrue(class_exists($class), "Missing {$class}. Run: php artisan mcp:tool:generate");
            $this->assertTrue(is_subclass_of($class, ApiTool::class), "{$class} is not an ApiTool");
            $this->assertSame($name, (new $class())->name());
        }

        $server = new EngineServer(new FakeTransporter());
        $merged = (new ReflectionClass($server))->getProperty('tools');
        $merged->setAccessible(true);
        /** @var array<int, class-string> $registered */
        $registered = $merged->getValue($server);

        foreach (self::GIT_TOOLS as $class) {
            $this->assertContains($class, $registered, "{$class} is not registered on EngineServer");
        }
    }

    public function test_git_tool_descriptions_contain_the_lock_tokens(): void
    {
        $caseSensitive = ['public_html', 'reset --hard', 'Pull first'];

        foreach (self::LOCKS as $name => $extra) {
            $class = self::GIT_TOOLS[$name];
            $this->assertTrue(class_exists($class), "Missing {$class}. Run: php artisan mcp:tool:generate");
            $description = (new $class())->description();
            $tokens = array_merge(self::PATH_LOCK, $extra);

            foreach ($tokens as $token) {
                if (in_array($token, $caseSensitive, true)) {
                    $this->assertStringContainsString(
                        $token,
                        $description,
                        "{$name} description must contain {$token}"
                    );
                    continue;
                }

                $this->assertStringContainsString(
                    strtolower($token),
                    strtolower($description),
                    "{$name} description must contain {$token}"
                );
            }
        }
    }

    public function test_readonly_mode_keeps_only_the_git_gets(): void
    {
        foreach (self::GIT_TOOLS as $class) {
            $this->assertTrue(class_exists($class), "Missing {$class}. Run: php artisan mcp:tool:generate");
        }

        $policy = new ToolPolicy([
            'toolsets' => 'all',
            'permission_mode' => 'readonly',
        ]);
        $names = array_map(
            fn (string $c): string => $policy->nameOf($c),
            $policy->filter(array_values(self::GIT_TOOLS))
        );

        $this->assertEqualsCanonicalizing(
            ['git_status', 'git_branches', 'git_commits'],
            $names
        );
    }

    public function test_no_git_operation_is_declared_read_only_post(): void
    {
        foreach (GenerateApiToolsCommand::READ_ONLY_OPERATIONS as $operation) {
            $this->assertStringNotContainsString(
                '/git/',
                $operation,
                "{$operation} must not be in READ_ONLY_OPERATIONS"
            );
        }
    }
}
