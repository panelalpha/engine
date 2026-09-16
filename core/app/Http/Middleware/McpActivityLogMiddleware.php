<?php

namespace App\Http\Middleware;

use App\Models\McpActivityLog;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every MCP tools/call into mcp_activity_logs.
 *
 * The hand-rolled McpController used to write this row itself. laravel/mcp owns
 * the JSON-RPC dispatch now, so the log is taken here instead, from the request
 * body on the way in and the JSON-RPC envelope on the way out.
 */
class McpActivityLogMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $body = json_decode((string)$request->getContent(), true);

        $toolName = is_array($body) && ($body['method'] ?? null) === 'tools/call'
            ? ($body['params']['name'] ?? null)
            : null;

        $response = $next($request);

        if (is_string($toolName) && $toolName !== '') {
            $this->record($request, $toolName, $body['params']['arguments'] ?? [], $response);
        }

        return $response;
    }

    private function record(Request $request, string $toolName, mixed $input, Response $response): void
    {
        [$status, $errorMessage] = $this->outcome($response);

        $token = $request->user()?->currentAccessToken();

        try {
            McpActivityLog::create([
                'token_id'      => $token instanceof PersonalAccessToken ? $token->id : null,
                'token_name'    => $token->name ?? 'unknown',
                'tool_name'     => $toolName,
                // Credentials are stripped by McpActivityLog's own mutator, so
                // no write path can forget to.
                'input'         => is_array($input) && $input !== [] ? $input : null,
                'status'        => $status,
                'error_message' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            // The tool has already run by the time we get here. Failing the
            // request now would report a completed -- possibly destructive --
            // operation as a 500 and invite the client to retry it.
            report($e);
        }
    }

    /**
     * A tool failure surfaces as result.isError; a dispatch failure (unknown
     * tool, bad params) surfaces as a JSON-RPC error object.
     *
     * @return array{0: string, 1: string|null}
     */
    private function outcome(Response $response): array
    {
        if (!$response instanceof \Illuminate\Http\JsonResponse) {
            return [$response->isSuccessful() ? 'success' : 'error', null];
        }

        $payload = $response->getData(true);

        if (isset($payload['error'])) {
            return ['error', (string)($payload['error']['message'] ?? 'Unknown error')];
        }

        if (($payload['result']['isError'] ?? false) === true) {
            return ['error', $this->firstText($payload['result']['content'] ?? [])];
        }

        return ['success', null];
    }

    /**
     * @param array<int, mixed> $content
     */
    private function firstText(array $content): ?string
    {
        foreach ($content as $item) {
            if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                return $item['text'];
            }
        }

        return null;
    }
}
