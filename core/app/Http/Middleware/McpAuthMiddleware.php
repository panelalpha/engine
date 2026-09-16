<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class McpAuthMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token instanceof PersonalAccessToken) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Accept tokens with 'mcp' ability or wildcard '*'
        if (!$token->can('mcp') && !$token->can('*')) {
            return new JsonResponse(['error' => 'Token does not have MCP access'], 401);
        }

        if ($token->isRevoked()) {
            return new JsonResponse(['error' => 'Token has been revoked'], 401);
        }

        return $next($request);
    }
}
