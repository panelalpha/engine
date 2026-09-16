<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Tools\Api\ApiTool;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\TestCase;

/**
 * Binary and streamed responses return false from getContent(), so a
 * download over MCP used to come back as 200 {data: null}.
 */
class ApiToolFileResponseTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'mcp_dl_') . '.txt';

        Route::get('/api/test-mcp/download', fn () => response()->download($this->file));
        Route::get('/api/test-mcp/stream', fn () => response()->stream(fn () => print('x')));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_a_download_comes_back_as_base64_with_its_metadata(): void
    {
        file_put_contents($this->file, "hello\0world");

        $response = $this->tool('/test-mcp/download')->handle(new Request());

        $this->assertFalse($response->isError());
        $payload = $this->payload($response);
        $this->assertSame(200, $payload['status']);
        $this->assertSame(basename($this->file), $payload['data']['filename']);
        $this->assertSame('text/plain', $payload['data']['mime_type']);
        $this->assertSame(11, $payload['data']['size']);
        $this->assertSame('base64', $payload['data']['encoding']);
        $this->assertSame("hello\0world", base64_decode($payload['data']['content']));
    }

    public function test_a_file_over_the_limit_is_an_error_not_a_truncation(): void
    {
        file_put_contents($this->file, str_repeat('a', ApiTool::MAX_DOWNLOAD_BYTES + 1));

        $response = $this->tool('/test-mcp/download')->handle(new Request());

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('larger than the 1 MB MCP download limit', (string)$response->content());
    }

    public function test_a_stream_is_refused_rather_than_read_as_null(): void
    {
        $response = $this->tool('/test-mcp/stream')->handle(new Request());

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('streams its response', (string)$response->content());
    }

    private function tool(string $path): ApiTool
    {
        return new class ($path) extends ApiTool {
            public function __construct(private string $apiPath)
            {
            }

            protected function method(): string
            {
                return 'GET';
            }

            protected function path(): string
            {
                return $this->apiPath;
            }
        };
    }

    /** @return array<string, mixed> */
    private function payload(Response $response): array
    {
        return json_decode((string)$response->content(), true);
    }
}
