<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Servers\EngineServer;
use App\Mcp\ToolPolicy;
use App\Mcp\Tools\MetricsLatestTool;
use App\Mcp\Tools\ProjectListSummaryTool;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use Tests\TestCase;

/**
 * The MCP server is served by laravel/mcp. These cover the wiring that the
 * hand-rolled McpController used to own: which tools exist, and under exactly
 * which names, since renaming one silently breaks every configured client.
 */
class EngineServerTest extends TestCase
{
    /** @return array<int, Tool> */
    private function tools(): array
    {
        $server = new ReflectionClass(EngineServer::class);

        return array_map(
            fn (string $class): Tool => new $class(),
            $server->getProperty('tools')->getDefaultValue()
        );
    }

    public function test_the_server_registers_exactly_the_read_only_tools(): void
    {
        $this->assertEqualsCanonicalizing(
            [MetricsLatestTool::class, ProjectListSummaryTool::class],
            (new ReflectionClass(EngineServer::class))->getProperty('tools')->getDefaultValue()
        );
    }

    public function test_tool_names_match_the_ones_clients_are_configured_with(): void
    {
        $this->assertSame(
            ['metrics_latest', 'project_list_summary'],
            array_map(fn (Tool $tool): string => $tool->name(), $this->tools())
        );
    }

    public function test_every_tool_advertises_itself_as_read_only(): void
    {
        foreach ($this->tools() as $tool) {
            $annotations = $tool->annotations();

            $this->assertTrue(
                $annotations['readOnlyHint'] ?? false,
                $tool->name() . ' must be annotated read-only'
            );
        }
    }

    public function test_every_tool_carries_a_description_for_the_model(): void
    {
        foreach ($this->tools() as $tool) {
            $this->assertNotSame('', trim($tool->description()));
        }
    }

    /**
     * call_engine_api proxied arbitrary authenticated /api/ calls with the
     * caller's bearer token, over a connection with TLS verification disabled
     * and a prefix check that no dot segment had to survive. It was dropped
     * deliberately; nothing should reintroduce it.
     */
    public function test_the_arbitrary_api_proxy_tool_is_gone(): void
    {
        $this->assertFalse(class_exists(\App\Lib\Mcp\Tools\CallEngineApiTool::class));

        foreach ($this->tools() as $tool) {
            $this->assertNotSame('call_engine_api', $tool->name());
        }
    }

    public function test_the_hand_rolled_json_rpc_implementation_is_gone(): void
    {
        $this->assertFalse(class_exists(\App\Http\Controllers\McpController::class));
        $this->assertFalse(class_exists(\App\Lib\Mcp\McpToolsRegistry::class));
        $this->assertFalse(class_exists(\App\Lib\Mcp\McpTool::class));
    }

    /**
     * The previous hand-rolled server answered 2024-11-05 to everyone, so that
     * is what every already-configured client opens with. laravel/mcp 1.x drops
     * the initialize handshake entirely; this pins us to a line that still
     * speaks it, and fails loudly if a future bump takes it away.
     */
    public function test_clients_on_every_supported_protocol_version_can_still_handshake(): void
    {
        foreach (['2024-11-05', '2025-03-26', '2025-06-18', '2025-11-25'] as $version) {
            $reply = $this->dispatch([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => $version,
                    'capabilities' => [],
                    'clientInfo' => ['name' => 'test', 'version' => '1'],
                ],
            ]);

            $this->assertArrayNotHasKey('error', $reply, "initialize failed for {$version}");
            $this->assertSame($version, $reply['result']['protocolVersion']);
            $this->assertSame('PanelAlpha Engine', $reply['result']['serverInfo']['name']);
        }
    }

    public function test_tools_list_advertises_the_hand_written_summary_tools(): void
    {
        $reply = $this->dispatch(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []]);

        // The generated per-endpoint API tools are listed alongside these and
        // are covered by GeneratedApiToolsTest.
        $names = array_column($reply['result']['tools'], 'name');

        $this->assertContains('metrics_latest', $names);
        $this->assertContains('project_list_summary', $names);
    }

    public function test_tools_list_returns_every_tool_in_one_unpaginated_page(): void
    {
        $reply = $this->dispatch(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/list', 'params' => []]);

        $this->assertArrayNotHasKey(
            'nextCursor',
            $reply['result'],
            'tools/list must fit one page: clients that ignore nextCursor lose every tool past it'
        );

        $server = new ReflectionClass(EngineServer::class);
        $registered = $server->getProperty('tools')->getDefaultValue();

        $generated = dirname($server->getFileName()) . '/../Tools/Api/generated-tools.php';

        if (is_file($generated)) {
            $registered = array_merge($registered, require $generated);
        }

        $this->assertCount(
            count((new ToolPolicy())->filter($registered)),
            $reply['result']['tools'],
            'the single page must hold the whole catalogue, not a truncated one'
        );
    }

    public function test_calling_the_dropped_proxy_tool_is_refused(): void
    {
        $reply = $this->dispatch([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'call_engine_api', 'arguments' => []],
        ]);

        $this->assertArrayHasKey('error', $reply);
        $this->assertStringContainsString('call_engine_api', $reply['error']['message']);
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function dispatch(array $message): array
    {
        $transport = new class implements \Laravel\Mcp\Server\Contracts\Transport
        {
            /** @var array<int, string> */
            public array $sent = [];

            public function onReceive(\Closure $handler): void {}

            public function run() {}

            public function send(string $message, ?string $sessionId = null): void
            {
                $this->sent[] = $message;
            }

            public function sessionId(): ?string
            {
                return 'test-session';
            }

            public function stream(\Closure $stream): void {}
        };

        $server = new EngineServer($transport);
        $server->start();
        $server->handle(json_encode($message, JSON_THROW_ON_ERROR));

        return json_decode($transport->sent[0], true, 512, JSON_THROW_ON_ERROR);
    }
}
