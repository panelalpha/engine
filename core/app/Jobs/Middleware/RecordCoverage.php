<?php

namespace App\Jobs\Middleware;

use App\Lib\Testing\CoverageRecorder;

class RecordCoverage
{
    public function handle(object $job, callable $next): mixed
    {
        $tokenId = null;
        if (method_exists($job, 'task')) {
            $raw = data_get($job->task()?->details, 'api_token_id');
            $tokenId = is_numeric($raw) ? (int) $raw : null;
        }

        return CoverageRecorder::around(
            $tokenId,
            static fn () => $next($job),
            'job',
        );
    }
}
