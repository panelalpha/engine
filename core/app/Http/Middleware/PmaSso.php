<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PmaSso
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * TrustProxies needs to be set for this to work
         */
        $requestFromPublicIp = (bool)filter_var(
            $request->ip(),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if ($requestFromPublicIp) {
            abort(new JsonResponse([
                'message' => 'Not found',
            ], 404));
        }
        return $next($request);
    }
}
