<?php

namespace Tests\Unit\Console;

use App\Console\Commands\Concerns\DispatchesApiRoute;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * `api:call` cannot fetch a file: a download answers with a BinaryFileResponse
 * or a StreamedResponse, and both return false from getContent(), which the
 * command reports as "failed to receive response content". These pin the part
 * of the file-transfer commands that exists to close that gap — that a binary
 * body actually reaches a local file, whole.
 */
class FileTransferCommandsTest extends TestCase
{
    private function command(BufferedOutput $buffer): Command
    {
        $command = new class extends Command {
            use DispatchesApiRoute;

            public function writeBody(Response $response, ?string $out): int
            {
                return $this->writeResponseBody($response, $out);
            }
        };
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        return $command;
    }

    public function test_a_binary_file_response_is_written_to_disk_whole(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'src');
        $payload = random_bytes(64 * 1024);
        file_put_contents($source, $payload);
        $out = tempnam(sys_get_temp_dir(), 'out');

        $buffer = new BufferedOutput();
        $command = $this->command($buffer);

        // The precondition for all of this: getContent() gives us nothing.
        $response = new BinaryFileResponse($source);
        $this->assertFalse($response->getContent());

        $this->assertSame(0, $command->writeBody($response, $out));
        $this->assertSame($payload, file_get_contents($out));
        $this->assertStringContainsString('Saved to', $buffer->fetch());

        unlink($source);
        unlink($out);
    }

    public function test_a_streamed_response_is_written_to_disk(): void
    {
        $out = tempnam(sys_get_temp_dir(), 'out');
        $buffer = new BufferedOutput();
        $command = $this->command($buffer);

        $response = new StreamedResponse(function (): void {
            echo 'first chunk';
            echo ' and second';
        });
        $this->assertFalse($response->getContent());

        $this->assertSame(0, $command->writeBody($response, $out));
        $this->assertSame('first chunk and second', file_get_contents($out));

        unlink($out);
    }

    public function test_an_error_response_reports_the_api_message_and_fails(): void
    {
        $buffer = new BufferedOutput();
        $command = $this->command($buffer);

        $response = new JsonResponse(['message' => 'Invalid path'], 404);

        $this->assertSame(1, $command->writeBody($response, null));
        $this->assertStringContainsString('HTTP 404: Invalid path', $buffer->fetch());
    }

    /**
     * The commands hardcode the URIs they dispatch to, so a route rename would
     * break them silently - nothing else references these paths.
     */
    public function test_the_routes_each_command_dispatches_to_still_exist(): void
    {
        $expected = [
            ['POST', 'api/projects/{username}/files/upload'],
            ['GET', 'api/projects/{username}/files/download'],
            ['GET', 'api/projects/{username}/domains/{domain}/log-files'],
            ['GET', 'api/projects/{username}/domains/{domain}/log-files/{filename}'],
            ['GET', 'api/modsec/audit-log/files'],
            ['GET', 'api/modsec/audit-log/files/{filename}'],
            ['GET', 'api/modsec/audit-log/files/{filename}/tail'],
        ];

        $registered = [];
        foreach (RouteFacade::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $registered[] = $method . ' ' . $route->uri();
            }
        }

        foreach ($expected as [$method, $uri]) {
            $this->assertContains(
                "{$method} {$uri}",
                $registered,
                "{$method} /{$uri} is gone; the command dispatching to it is now dead"
            );
        }
    }

    public function test_a_json_body_is_written_to_disk_when_an_out_path_is_given(): void
    {
        $out = tempnam(sys_get_temp_dir(), 'out');
        $buffer = new BufferedOutput();
        $command = $this->command($buffer);

        $this->assertSame(0, $command->writeBody(new JsonResponse(['data' => [1, 2]]), $out));
        $this->assertSame('{"data":[1,2]}', file_get_contents($out));

        unlink($out);
    }
}
