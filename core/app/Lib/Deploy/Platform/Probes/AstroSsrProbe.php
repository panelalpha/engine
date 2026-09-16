<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * Whether an Astro project builds a Node server or a directory of HTML:
 * `@astrojs/node`, or `output: server`/`hybrid`, or a `start` script that is
 * not the local preview.
 */
final class AstroSsrProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'astro-ssr';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        $config = $context->configContents('astro.config') ?? '';

        // An explicit `output: 'static'` settles it, whatever else is present.
        if (preg_match('/output\s*:\s*[\'"]static[\'"]/', $config) === 1) {
            return false;
        }

        if ($context->hasDep('@astrojs/node')
            || preg_match('/output\s*:\s*[\'"](server|hybrid)[\'"]/', $config) === 1
        ) {
            return true;
        }

        // "start": "astro dev" is the local preview, not a production server.
        $start = $context->script('start');

        return $start !== '' && preg_match('/\bastro\s+(dev|preview)\b/', $start) !== 1;
    }
}
